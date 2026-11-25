<?php

declare(strict_types=1);

namespace AIC\Schema\Contracts;

use InvalidArgumentException;

/**
 * Every column type a migration can name, one method each (#86): the
 * vocabulary of `TableBuilder` and of `TableAlteration::addColumn()` and
 * `changeType()`.
 */
interface ColumnTypes
{
    /**
     * @return ColumnType An integer the database generates for each row.
     */
    public function identity(): ColumnType;

    /**
     * @param int $length The longest value, in characters.
     *
     * @return ColumnType Text up to that length.
     */
    public function string(int $length): ColumnType;

    /**
     * @return ColumnType Text of any length.
     */
    public function text(): ColumnType;

    /**
     * @return ColumnType A whole number.
     */
    public function integer(): ColumnType;

    /**
     * @return ColumnType A 64-bit whole number: the type of a column that refers to an identity.
     */
    public function bigInteger(): ColumnType;

    /**
     * @param int $precision Its fractional seconds' digits, 0 to 6 (#339); 6, Postgres's own, by default.
     *
     * @return ColumnType A point in time with its time zone.
     */
    public function timestampTz(int $precision = 6): ColumnType;

    /**
     * @return ColumnType A calendar date with no time or zone.
     */
    public function date(): ColumnType;

    /**
     * @param int $precision Its digits in all, 1 to 1000.
     * @param int $scale     Its digits after the point, 0 to the precision.
     *
     * @return ColumnType An exact decimal, such as money.
     */
    public function numeric(int $precision, int $scale): ColumnType;

    /**
     * @return ColumnType True or false.
     */
    public function boolean(): ColumnType;

    /**
     * @return ColumnType A UUID (#307).
     */
    public function uuid(): ColumnType;

    /**
     * @return ColumnType A UUID key the database generates for each row that names none (#307).
     */
    public function uuidKey(): ColumnType;

    /**
     * @return ColumnType A JSON document, stored parsed (#307).
     */
    public function jsonb(): ColumnType;

    /**
     * @param list<string> $choices The values it may hold, each 1 to 63 letters, digits, `_`, `.`, `:` or `-`.
     *
     * @return ColumnType Text that is one of them, checked by the database (#307).
     */
    public function choice(array $choices): ColumnType;

    /**
     * @param ColumnType $columnType The column's type: any but a key, a choice or another generated column.
     * @param Term       $term       What it holds, computed from the row's other columns (#310).
     *
     * @throws InvalidArgumentException When the type cannot be generated.
     *
     * @return ColumnType A column the database computes and stores on every write; nothing writes it.
     */
    public function generated(ColumnType $columnType, Term $term): ColumnType;

    /**
     * @param ColumnType $columnType The column's type: any but a key or a generated column.
     * @param Term       $term       What a row that names no value holds (#308), such as a state machine's initial
     *                               state; a column added to a table with rows takes it on each.
     *
     * @throws InvalidArgumentException When the type cannot take a default, or has one.
     *
     * @return ColumnType The type with its default.
     */
    public function defaulted(ColumnType $columnType, Term $term): ColumnType;
}
