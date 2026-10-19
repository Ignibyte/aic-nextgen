<?php

declare(strict_types=1);

namespace AIC\Schema\Contracts;

/**
 * A new table's columns, in order, and its primary key. Each method returns the
 * builder, so a migration chains them; a column is required unless
 * `nullable()` follows it.
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
}
