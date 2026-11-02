<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use AIC\Workflow\Domain\CodeEdge;
use AIC\Workflow\Domain\CodeSymbol;
use AIC\Workflow\Exception\WorkflowRefusal;
use PDO;
use PDOException;
use stdClass;

use function strrpos;
use function substr;

/**
 * Writes the code index (#106, droost-engine's `rebuild` and `applyDelta`):
 * a file's rows replaced as one, a removed file's dropped, the surface's
 * rows replaced when its hash changes. Everything one refresh writes is one
 * transaction.
 */
final readonly class CodeIndexStore
{
    /**
     * A symbol's insert.
     */
    private const string SYMBOL = 'INSERT INTO symbol (name, short, kind, file, line, area, detail, returns, public, origin)'
        . ' VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';

    /**
     * An edge's insert.
     */
    private const string EDGE = 'INSERT INTO edge (source, target, kind, file, origin) VALUES (?, ?, ?, ?, ?)';

    /**
     * What removing a file deletes: its row, and the symbols and edges its PHP gave.
     */
    private const array REMOVE = [
        'DELETE FROM file WHERE path = ?',
        "DELETE FROM symbol WHERE file = ? AND origin = 'php'",
        "DELETE FROM edge WHERE file = ? AND origin = 'php'",
    ];

    /**
     * @param PDO             $pdo             The index, open.
     * @param CodeIndexSchema $codeIndexSchema Runs its statements.
     */
    public function __construct(
        private PDO $pdo,
        private CodeIndexSchema $codeIndexSchema,
    ) {
    }

    /**
     * @throws WorkflowRefusal When the index cannot be read.
     *
     * @return array<string, string> Each indexed file's hash, by path.
     */
    public function files(): array
    {
        $files = [];

        try {
            $statement = $this->codeIndexSchema->statement($this->pdo, 'SELECT path, hash FROM file', []);

            while (($row = $statement->fetchObject()) instanceof stdClass) {
                $jsonReader = new JsonReader($row, CodeIndexSchema::DIRECTORY . '/code.sqlite');
                $files[$jsonReader->string('path')] = $jsonReader->string('hash');
            }
        } catch (PDOException $pdoException) {
            throw new WorkflowRefusal('the code index could not be read: ' . $pdoException->getMessage(), 0, $pdoException);
        }

        return $files;
    }

    /**
     * @param string           $path    A file.
     * @param string           $hash    Its hash.
     * @param string           $area    Its area.
     * @param list<CodeSymbol> $symbols Its symbols.
     * @param list<CodeEdge>   $edges   Its edges.
     *
     * @throws PDOException When it cannot be written.
     */
    public function file(string $path, string $hash, string $area, array $symbols, array $edges): void
    {
        $this->remove($path);
        $this->codeIndexSchema->statement($this->pdo, 'INSERT INTO file (path, hash, area) VALUES (?, ?, ?)', [$path, $hash, $area]);
        $this->rows($symbols, $edges, 'php');
    }

    /**
     * @param string $path A file the index no longer holds.
     *
     * @throws PDOException When it cannot be written.
     */
    public function remove(string $path): void
    {
        foreach (self::REMOVE as $sql) {
            $this->codeIndexSchema->statement($this->pdo, $sql, [$path]);
        }
    }

    /**
     * @param string           $hash    The surface's hash.
     * @param list<CodeSymbol> $symbols Its pseudo-symbols.
     * @param list<CodeEdge>   $edges   Its edges.
     *
     * @throws PDOException When it cannot be written.
     */
    public function surface(string $hash, array $symbols, array $edges): void
    {
        $this->codeIndexSchema->statement($this->pdo, "DELETE FROM symbol WHERE origin = 'surface'", []);
        $this->codeIndexSchema->statement($this->pdo, "DELETE FROM edge WHERE origin = 'surface'", []);
        $this->codeIndexSchema->statement($this->pdo, 'INSERT OR REPLACE INTO meta (key, value) VALUES (?, ?)', ['surface', $hash]);
        $this->rows($symbols, $edges, 'surface');
    }

    /**
     * @throws PDOException When it cannot be read.
     *
     * @return string The surface's hash the index holds; empty before the first.
     */
    public function surfaceHash(): string
    {
        return (string) $this->codeIndexSchema->statement($this->pdo, "SELECT value FROM meta WHERE key = 'surface'", [])->fetchColumn();
    }

    /**
     * @throws PDOException When it cannot be read.
     *
     * @return array{int, int} How many symbols and edges the index holds.
     */
    public function counts(): array
    {
        return [
            (int) $this->codeIndexSchema->statement($this->pdo, 'SELECT COUNT(*) FROM symbol', [])->fetchColumn(),
            (int) $this->codeIndexSchema->statement($this->pdo, 'SELECT COUNT(*) FROM edge', [])->fetchColumn(),
        ];
    }

    /**
     * @param list<CodeSymbol> $symbols Symbols.
     * @param list<CodeEdge>   $edges   Edges.
     * @param string           $origin  `php` or `surface`.
     *
     * @throws PDOException When they cannot be written.
     */
    private function rows(array $symbols, array $edges, string $origin): void
    {
        foreach ($symbols as $symbol) {
            $this->codeIndexSchema->statement($this->pdo, self::SYMBOL, [
                $symbol->name,
                $this->short($symbol->name),
                $symbol->kind,
                $symbol->file,
                $symbol->line,
                $symbol->area,
                $symbol->detail,
                $symbol->returns,
                $symbol->public ? 1 : 0,
                $origin,
            ]);
        }

        foreach ($edges as $edge) {
            $this->codeIndexSchema->statement($this->pdo, self::EDGE, [$edge->source, $edge->target, $edge->kind, $edge->file, $origin]);
        }
    }

    /**
     * @param string $name A full name.
     *
     * @return string Its last segment: a class's short name, a method's name.
     */
    private function short(string $name): string
    {
        $cut = strrpos($name, '::');
        $cut = $cut === false
            ? strrpos($name, '\\')
            : $cut + 1;

        return $cut === false
            ? $name
            : substr($name, $cut + 1);
    }
}
