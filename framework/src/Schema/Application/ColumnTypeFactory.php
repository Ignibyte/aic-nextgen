<?php

declare(strict_types=1);

namespace AIC\Schema\Application;

use AIC\Schema\Contracts\ColumnType;
use AIC\Schema\Contracts\ColumnTypes;
use AIC\Schema\Contracts\LogicalType;
use InvalidArgumentException;
use Override;

/**
 * The ColumnTypes a SchemaPlan hands its migrations and its TableDrafts.
 */
final readonly class ColumnTypeFactory implements ColumnTypes
{
    /**
     * @throws InvalidArgumentException When a constructed value or argument is invalid.
     *
     * @return ColumnType An integer the database generates for each row.
     */
    #[Override]
    public function identity(): ColumnType
    {
        return new ColumnType(LogicalType::BigIdentity, 0, 0);
    }

    /**
     * @param int $length The longest value, in characters.
     *
     * @throws InvalidArgumentException When the length is not positive.
     *
     * @return ColumnType Text up to that length.
     */
    #[Override]
    public function string(int $length): ColumnType
    {
        return new ColumnType(LogicalType::String, $length, 0);
    }

    /**
     * @throws InvalidArgumentException When a constructed value or argument is invalid.
     *
     * @return ColumnType Text of any length.
     */
    #[Override]
    public function text(): ColumnType
    {
        return new ColumnType(LogicalType::Text, 0, 0);
    }

    /**
     * @throws InvalidArgumentException When a constructed value or argument is invalid.
     *
     * @return ColumnType A whole number.
     */
    #[Override]
    public function integer(): ColumnType
    {
        return new ColumnType(LogicalType::Integer, 0, 0);
    }

    /**
     * @throws InvalidArgumentException When a constructed value or argument is invalid.
     *
     * @return ColumnType A 64-bit whole number.
     */
    #[Override]
    public function bigInteger(): ColumnType
    {
        return new ColumnType(LogicalType::BigInteger, 0, 0);
    }

    /**
     * @throws InvalidArgumentException When a constructed value or argument is invalid.
     *
     * @return ColumnType A point in time with its time zone.
     */
    #[Override]
    public function timestampTz(): ColumnType
    {
        return new ColumnType(LogicalType::TimestampTz, 0, 0);
    }

    /**
     * @throws InvalidArgumentException When a constructed value or argument is invalid.
     *
     * @return ColumnType A calendar date with no time or zone.
     */
    #[Override]
    public function date(): ColumnType
    {
        return new ColumnType(LogicalType::Date, 0, 0);
    }

    /**
     * @param int $precision Its digits in all, 1 to 1000.
     * @param int $scale     Its digits after the point, 0 to the precision.
     *
     * @throws InvalidArgumentException When the precision or the scale is out of range.
     *
     * @return ColumnType An exact decimal.
     */
    #[Override]
    public function numeric(int $precision, int $scale): ColumnType
    {
        return new ColumnType(LogicalType::Numeric, $precision, $scale);
    }

    /**
     * @throws InvalidArgumentException When a constructed value or argument is invalid.
     *
     * @return ColumnType True or false.
     */
    #[Override]
    public function boolean(): ColumnType
    {
        return new ColumnType(LogicalType::Boolean, 0, 0);
    }
}
