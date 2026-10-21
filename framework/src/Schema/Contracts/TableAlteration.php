<?php

declare(strict_types=1);

namespace AIC\Schema\Contracts;

/**
 * Unique constraints and indexes to add to a table an earlier migration
 * created. Each method returns the builder, so a migration chains them; the
 * database checks the columns when the migration runs.
 */
interface TableAlteration
{
    /**
     * @param list<string> $columns Columns whose values, taken together, no two rows may share.
     *
     * @return self The builder.
     */
    public function unique(array $columns): self;

    /**
     * @param list<string> $columns The columns to index, in order.
     *
     * @return self The builder.
     */
    public function index(array $columns): self;
}
