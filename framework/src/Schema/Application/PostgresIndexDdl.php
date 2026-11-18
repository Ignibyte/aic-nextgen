<?php

declare(strict_types=1);

namespace AIC\Schema\Application;

use AIC\Schema\Contracts\IndexMethod;
use AIC\Schema\Domain\ColumnGroup;
use AIC\Schema\Domain\Constraints;
use AIC\Schema\Domain\Identifier;
use AIC\Schema\Domain\MethodIndex;
use AIC\Schema\Domain\PartialIndex;
use InvalidArgumentException;

use function array_map;
use function array_merge;
use function implode;

/**
 * Postgres `CREATE INDEX` for a table's indexes: the ordered ones, then the
 * partial ones, unique or not, then those of another method (#310).
 */
final readonly class PostgresIndexDdl
{
    /**
     * @param Identifier  $identifier  The table.
     * @param Constraints $constraints Its constraints.
     *
     * @throws InvalidArgumentException When an index name would be longer than an identifier may be.
     *
     * @return list<string> A `CREATE INDEX` for each of its indexes, in that order.
     */
    public function indexes(Identifier $identifier, Constraints $constraints): array
    {
        return array_merge(
            $this->plain($identifier, $constraints),
            $this->partials($identifier, $constraints),
            $this->methods($identifier, $constraints),
        );
    }

    /**
     * @param Identifier  $identifier  The table.
     * @param Constraints $constraints Its constraints.
     *
     * @throws InvalidArgumentException When an index name would be longer than an identifier may be.
     *
     * @return list<string> A `CREATE INDEX` for each ordered index, in order.
     */
    private function plain(Identifier $identifier, Constraints $constraints): array
    {
        $statements = [];

        foreach ($constraints->indexes as $index) {
            if (!$index instanceof ColumnGroup) {
                continue;
            }

            $statements[] = 'CREATE INDEX ' . $index->indexName($identifier)->value . ' ON ' . $identifier->value
                . ' (' . implode(', ', $index->columns) . ')';
        }

        return $statements;
    }

    /**
     * @param Identifier  $identifier  The table.
     * @param Constraints $constraints Its constraints.
     *
     * @throws InvalidArgumentException When an index name would be longer than an identifier may be.
     *
     * @return list<string> A `CREATE [UNIQUE] INDEX ... WHERE` for each partial index, in order.
     */
    private function partials(Identifier $identifier, Constraints $constraints): array
    {
        $statements = [];

        foreach ($constraints->partialIndexes as $partialIndex) {
            if (!$partialIndex instanceof PartialIndex) {
                continue;
            }

            $statements[] = $this->partial($identifier, $partialIndex);
        }

        return $statements;
    }

    /**
     * @param Identifier  $identifier  The table.
     * @param Constraints $constraints Its constraints.
     *
     * @throws InvalidArgumentException When an index name would be longer than an identifier may be.
     *
     * @return list<string> A `CREATE INDEX ... USING` for each index of another method, in order.
     */
    private function methods(Identifier $identifier, Constraints $constraints): array
    {
        $statements = [];

        foreach ($constraints->methodIndexes as $methodIndex) {
            if (!$methodIndex instanceof MethodIndex) {
                continue;
            }

            $statements[] = $this->method($identifier, $methodIndex);
        }

        return $statements;
    }

    /**
     * @param Identifier   $identifier   The table.
     * @param PartialIndex $partialIndex A partial index on it.
     *
     * @throws InvalidArgumentException When its name would be longer than an identifier may be.
     *
     * @return string Its `CREATE [UNIQUE] INDEX ... WHERE`.
     */
    private function partial(Identifier $identifier, PartialIndex $partialIndex): string
    {
        $create = $partialIndex->unique
            ? 'CREATE UNIQUE INDEX '
            : 'CREATE INDEX ';

        return $create . $partialIndex->indexName($identifier)->value . ' ON ' . $identifier->value
            . ' (' . implode(', ', $partialIndex->columnGroup->columns) . ') WHERE ' . $partialIndex->predicate->sql;
    }

    /**
     * @param Identifier  $identifier  The table.
     * @param MethodIndex $methodIndex An index of another method on it.
     *
     * @throws InvalidArgumentException When its name would be longer than an identifier may be.
     *
     * @return string Its `CREATE INDEX ... USING`, each column with its operator class where the method needs one.
     */
    private function method(Identifier $identifier, MethodIndex $methodIndex): string
    {
        [$method, $operatorClass] = match ($methodIndex->indexMethod) {
            IndexMethod::Hash => ['hash', ''],
            IndexMethod::Gin => ['gin', ''],
            IndexMethod::Gist => ['gist', ''],
            IndexMethod::Brin => ['brin', ''],
            IndexMethod::Trigram => ['gin', ' gin_trgm_ops'],
            IndexMethod::JsonPath => ['gin', ' jsonb_path_ops'],
        };
        $columns = array_map(static fn (string $column): string => $column . $operatorClass, $methodIndex->columnGroup->columns);

        return 'CREATE INDEX ' . $methodIndex->indexName($identifier)->value . ' ON ' . $identifier->value
            . ' USING ' . $method . ' (' . implode(', ', $columns) . ')';
    }
}
