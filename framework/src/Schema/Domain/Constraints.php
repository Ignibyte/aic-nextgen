<?php

declare(strict_types=1);

namespace AIC\Schema\Domain;

/**
 * A table's unique constraints, indexes, foreign keys and checks: declared
 * with the table, or added to it later.
 */
final readonly class Constraints
{
    /**
     * @param list<object> $uniques        Unique constraints, ColumnGroups.
     * @param list<object> $indexes        Indexes, ColumnGroups.
     * @param list<object> $foreignKeys    ForeignKeys.
     * @param list<object> $checks         Checks (#310).
     * @param list<object> $partialIndexes PartialIndexes, unique or not (#310).
     * @param list<object> $methodIndexes  MethodIndexes (#310).
     */
    public function __construct(
        public array $uniques,
        public array $indexes,
        public array $foreignKeys,
        public array $checks,
        public array $partialIndexes,
        public array $methodIndexes,
    ) {
    }

    /**
     * @return bool Whether there are none.
     */
    public function isEmpty(): bool
    {
        return $this->uniques === [] && $this->indexes === [] && $this->foreignKeys === [] && $this->checks === []
            && $this->partialIndexes === [] && $this->methodIndexes === [];
    }
}
