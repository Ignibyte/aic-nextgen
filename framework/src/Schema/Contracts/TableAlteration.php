<?php

declare(strict_types=1);

namespace AIC\Schema\Contracts;

/**
 * Changes to a table an earlier migration created: columns to add, columns
 * whose type changes, and unique constraints and indexes. Each method returns
 * the builder, so a migration chains them; the database checks the columns
 * when the migration runs.
 */
interface TableAlteration
{
    /**
     * @param string     $name       The new column. It is required unless `nullable()` follows, and a required
     *                               column cannot be added to a table that has rows.
     * @param ColumnType $columnType Its type.
     *
     * @return self The builder.
     */
    public function addColumn(string $name, ColumnType $columnType): self;

    /**
     * Lets the column just added hold null.
     *
     * @return self The builder.
     */
    public function nullable(): self;

    /**
     * @param string     $column     An existing column; its nullability stays as it is.
     * @param ColumnType $columnType Its new type, any but an identity.
     * @param string     $using      SQL that converts each value when the database cannot cast it, such as
     *                               `(added_on AT TIME ZONE 'UTC')::date`; empty for a plain cast.
     *
     * @return self The builder.
     */
    public function changeType(string $column, ColumnType $columnType, string $using = ''): self;

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
