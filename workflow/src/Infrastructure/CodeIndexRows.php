<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use AIC\Workflow\Domain\CodeEdge;
use AIC\Workflow\Domain\CodeSymbol;
use AIC\Workflow\Exception\WorkflowRefusal;
use PDOException;
use stdClass;

/**
 * Reads rows from the code index (#106; shared since #232): a query's rows,
 * and its symbol and edge rows as values. Every argument reaches SQL bound.
 */
final readonly class CodeIndexRows
{
    /**
     * A symbol row's columns.
     */
    public const string SYMBOL = 'SELECT name, kind, file, line, area, detail, returns, public FROM symbol';

    /**
     * An edge row's columns.
     */
    public const string EDGE = 'SELECT source, target, kind, file FROM edge';

    /**
     * @param string          $root            The project's root.
     * @param CodeIndexSchema $codeIndexSchema Opens the index.
     */
    public function __construct(
        private string $root,
        private CodeIndexSchema $codeIndexSchema = new CodeIndexSchema(),
    ) {
    }

    /**
     * @param string           $sql        A symbol query.
     * @param list<string|int> $parameters Its values.
     *
     * @throws WorkflowRefusal When the index cannot be read.
     *
     * @return list<CodeSymbol> Its rows.
     */
    public function symbols(string $sql, array $parameters): array
    {
        $symbols = [];

        foreach ($this->rows($sql, $parameters) as $jsonReader) {
            $symbols[] = new CodeSymbol(
                $jsonReader->string('name'),
                $jsonReader->string('kind'),
                $jsonReader->string('file'),
                $jsonReader->int('line'),
                $jsonReader->string('area'),
                $jsonReader->string('detail'),
                $jsonReader->string('returns'),
                $jsonReader->int('public') === 1,
            );
        }

        return $symbols;
    }

    /**
     * @param string           $sql        An edge query.
     * @param list<string|int> $parameters Its values.
     *
     * @throws WorkflowRefusal When the index cannot be read.
     *
     * @return list<CodeEdge> Its rows.
     */
    public function edges(string $sql, array $parameters): array
    {
        $edges = [];

        foreach ($this->rows($sql, $parameters) as $jsonReader) {
            $edges[] = new CodeEdge(
                $jsonReader->string('source'),
                $jsonReader->string('target'),
                $jsonReader->string('kind'),
                $jsonReader->string('file'),
            );
        }

        return $edges;
    }

    /**
     * @param string           $sql        A query.
     * @param list<string|int> $parameters Its values.
     *
     * @throws WorkflowRefusal When the index cannot be read.
     *
     * @return list<JsonReader> Its rows.
     */
    public function rows(string $sql, array $parameters): array
    {
        try {
            $statement = $this->codeIndexSchema->statement($this->codeIndexSchema->open($this->root, false), $sql, $parameters);
            $rows = [];

            while (($row = $statement->fetchObject()) instanceof stdClass) {
                $rows[] = new JsonReader($row, CodeIndexSchema::DIRECTORY . '/code.sqlite');
            }
        } catch (PDOException $pdoException) {
            throw new WorkflowRefusal('the code index could not be read: ' . $pdoException->getMessage(), 0, $pdoException);
        }

        return $rows;
    }
}
