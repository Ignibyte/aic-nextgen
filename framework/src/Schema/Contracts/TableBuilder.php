<?php

declare(strict_types=1);

namespace AIC\Schema\Contracts;

/**
 * A new table's columns, in order, its primary key, and its unique
 * constraints, indexes and foreign keys. Each method returns the builder, so a
 * migration chains them; a column is required unless `nullable()` follows it.
 */
interface TableBuilder
{
    /**
     * @param string $name The column, an integer the database generates for each row.
     *
     * @return self The builder.
     */
    public function identity(string $name): self;

    /**
     * @param string $name   The column.
     * @param int    $length Its longest value, in characters.
     *
     * @return self The builder.
     */
    public function string(string $name, int $length): self;

    /**
     * @param string $name The column.
     *
     * @return self The builder.
     */
    public function text(string $name): self;

    /**
     * @param string $name The column, a whole number.
     *
     * @return self The builder.
     */
    public function integer(string $name): self;

    /**
     * @param string $name The column, a 64-bit whole number: the type of a column that refers to an identity.
     *
     * @return self The builder.
     */
    public function bigInteger(string $name): self;

    /**
     * @param string $name The column, a point in time with its time zone.
     *
     * @return self The builder.
     */
    public function timestampTz(string $name): self;

    /**
     * Lets the last column added hold null.
     *
     * @return self The builder.
     */
    public function nullable(): self;

    /**
     * @param list<string> $columns The primary key's columns.
     *
     * @return self The builder.
     */
    public function primary(array $columns): self;

    /**
     * @param list<string> $columns Columns whose values, taken together, no two rows share.
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

    /**
     * @param string   $column        The referring column, on this table.
     * @param string   $table         The table it refers to.
     * @param string   $foreignColumn The column it refers to there, its primary key or a unique column.
     * @param OnDelete $onDelete      What deleting the row it refers to does here.
     *
     * @return self The builder.
     */
    public function foreignKey(string $column, string $table, string $foreignColumn, OnDelete $onDelete): self;
}
