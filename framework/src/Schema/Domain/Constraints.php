<?php

declare(strict_types=1);

namespace AIC\Schema\Domain;

/**
 * A table's unique constraints, indexes and foreign keys: declared with the
 * table, or added to it later.
 */
final readonly class Constraints
{
    /**
     * @param list<object> $uniques     Unique constraints, ColumnGroups.
     * @param list<object> $indexes     Indexes, ColumnGroups.
     * @param list<object> $foreignKeys ForeignKeys.
     */
    public function __construct(
        public array $uniques,
        public array $indexes,
        public array $foreignKeys,
    ) {
    }

    /**
     * @return bool Whether there are none.
     */
    public function isEmpty(): bool
    {
        return $this->uniques === [] && $this->indexes === [] && $this->foreignKeys === [];
    }
}
