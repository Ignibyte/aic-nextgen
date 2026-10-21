<?php

declare(strict_types=1);

namespace AIC\Schema\Domain;

use InvalidArgumentException;

/**
 * Changes to a table that already exists: columns to add, columns whose type
 * changes, and constraints and indexes to add. Its columns are the
 * database's to check: the migration fails, and is rolled back, when one is
 * missing.
 */
final readonly class Alteration
{
    /**
     * @param Identifier   $table       The existing table.
     * @param list<object> $added       Columns to add, in order: Columns.
     * @param list<object> $retyped     Columns whose type changes, in order: Retypes.
     * @param Constraints  $constraints Constraints and indexes to add.
     *
     * @throws InvalidArgumentException When it changes nothing.
     */
    public function __construct(
        public Identifier $table,
        public array $added,
        public array $retyped,
        public Constraints $constraints,
    ) {
        if ($added === [] && $retyped === [] && $constraints->isEmpty()) {
            throw new InvalidArgumentException(
                'Table ' . $table->value . ': an alteration adds a column, changes a type, or adds a constraint or an index.',
            );
        }
    }
}
