<?php

declare(strict_types=1);

namespace AIC\Schema\Domain;

use InvalidArgumentException;

/**
 * Constraints and indexes to add to a table that already exists. Its columns
 * are the database's to check: the migration fails, and is rolled back, when
 * one is missing.
 */
final readonly class Alteration
{
    /**
     * @param Identifier  $table       The existing table.
     * @param Constraints $constraints What to add to it.
     *
     * @throws InvalidArgumentException When it adds nothing.
     */
    public function __construct(
        public Identifier $table,
        public Constraints $constraints,
    ) {
        if ($constraints->isEmpty()) {
            throw new InvalidArgumentException('Table ' . $table->value . ': an alteration adds a constraint or an index.');
        }
    }
}
