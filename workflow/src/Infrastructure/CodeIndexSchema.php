<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use AIC\Workflow\Exception\WorkflowRefusal;
use PDO;
use PDOException;
use PDOStatement;

/**
 * The code index's file and schema (#106): `var/index/code.sqlite`, a cache
 * beside the run's state, not in it. A stored version other than this
 * engine's is dropped and built again whole (droost-engine's rule), so an
 * index from another engine never answers.
 */
final readonly class CodeIndexSchema
{
    /**
     * The index's directory, relative to the project.
     */
    public const string DIRECTORY = 'var/index';

    /**
     * The index's version.
     */
    private const int VERSION = 1;

    /**
     * Version 1: files, symbols, edges and the surface's hash.
     */
    private const string TABLES = <<<'SQL'
        DROP TABLE IF EXISTS file;
        DROP TABLE IF EXISTS symbol;
        DROP TABLE IF EXISTS edge;
        DROP TABLE IF EXISTS meta;
        CREATE TABLE file (path TEXT PRIMARY KEY, hash TEXT NOT NULL, area TEXT NOT NULL);
        CREATE TABLE symbol (
            name TEXT NOT NULL,
            short TEXT NOT NULL,
            kind TEXT NOT NULL,
            file TEXT NOT NULL,
            line INTEGER NOT NULL,
            area TEXT NOT NULL,
            detail TEXT NOT NULL,
            origin TEXT NOT NULL
        );
        CREATE INDEX symbol_name ON symbol (name);
        CREATE INDEX symbol_short ON symbol (short COLLATE NOCASE);
        CREATE INDEX symbol_file ON symbol (file);
        CREATE TABLE edge (source TEXT NOT NULL, target TEXT NOT NULL, kind TEXT NOT NULL, file TEXT NOT NULL, origin TEXT NOT NULL);
        CREATE INDEX edge_target ON edge (target);
        CREATE INDEX edge_source ON edge (source);
        CREATE INDEX edge_file ON edge (file);
        CREATE TABLE meta (key TEXT PRIMARY KEY, value TEXT NOT NULL);
        PRAGMA user_version = 1;
        SQL;

    /**
     * @param AtomicFile       $atomicFile       Makes the directory.
     * @param SqliteStatements $sqliteStatements Runs its statements.
     */
    public function __construct(
        private AtomicFile $atomicFile = new AtomicFile(),
        private SqliteStatements $sqliteStatements = new SqliteStatements(),
    ) {
    }

    /**
     * @param string $root The project's root.
     * @param bool   $wipe Whether to drop what it holds and start again.
     *
     * @throws WorkflowRefusal When the index cannot be opened.
     *
     * @return PDO The index, at this engine's version.
     */
    public function open(string $root, bool $wipe): PDO
    {
        $this->atomicFile->directory($root . '/' . self::DIRECTORY);

        try {
            $pdo = new PDO('sqlite:' . $root . '/' . self::DIRECTORY . '/code.sqlite');
            $pdo->exec('PRAGMA busy_timeout = 5000');
            $version = (int) $this->statement($pdo, 'PRAGMA user_version', [])->fetchColumn();

            if ($wipe || $version !== self::VERSION) {
                $pdo->exec(self::TABLES);
            }
        } catch (PDOException $pdoException) {
            throw new WorkflowRefusal(
                self::DIRECTORY . '/code.sqlite could not be opened: ' . $pdoException->getMessage(),
                0,
                $pdoException,
            );
        }

        return $pdo;
    }

    /**
     * @param PDO                    $pdo        The index.
     * @param string                 $sql        A statement.
     * @param list<string|int|float> $parameters Its values, bound.
     *
     * @throws PDOException When it fails.
     *
     * @return PDOStatement It, executed.
     */
    public function statement(PDO $pdo, string $sql, array $parameters): PDOStatement
    {
        return $this->sqliteStatements->run($pdo, $sql, $parameters);
    }
}
