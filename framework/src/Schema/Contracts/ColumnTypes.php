<?php

declare(strict_types=1);

namespace AIC\Schema\Contracts;

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
     * @return ColumnType A point in time with its time zone.
     */
    public function timestampTz(): ColumnType;

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
}
