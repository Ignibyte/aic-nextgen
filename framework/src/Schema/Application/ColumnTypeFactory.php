<?php

declare(strict_types=1);

namespace AIC\Schema\Application;

use AIC\Schema\Contracts\ColumnType;
use AIC\Schema\Contracts\ColumnTypes;
use AIC\Schema\Contracts\LogicalType;
use AIC\Schema\Contracts\Term;
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

    /**
     * @throws InvalidArgumentException When a constructed value or argument is invalid.
     *
     * @return ColumnType A UUID (#307).
     */
    #[Override]
    public function uuid(): ColumnType
    {
        return new ColumnType(LogicalType::Uuid, 0, 0);
    }

    /**
     * @throws InvalidArgumentException When a constructed value or argument is invalid.
     *
     * @return ColumnType A UUID key the database generates for each row that names none (#307).
     */
    #[Override]
    public function uuidKey(): ColumnType
    {
        return new ColumnType(LogicalType::UuidKey, 0, 0);
    }

    /**
     * @throws InvalidArgumentException When a constructed value or argument is invalid.
     *
     * @return ColumnType A JSON document, stored parsed (#307).
     */
    #[Override]
    public function jsonb(): ColumnType
    {
        return new ColumnType(LogicalType::Jsonb, 0, 0);
    }

    /**
     * @param list<string> $choices The values it may hold.
     *
     * @throws InvalidArgumentException When there are none, one is repeated or one is not 1 to 63 letters, digits,
     *                                  `_`, `.`, `:` or `-`.
     *
     * @return ColumnType Text that is one of them, checked by the database (#307).
     */
    #[Override]
    public function choice(array $choices): ColumnType
    {
        return new ColumnType(LogicalType::Choice, 0, 0, $choices);
    }

    /**
     * @param ColumnType $columnType The column's type: any but a key, a choice or another generated column.
     * @param Term       $term       What it holds (#310).
     *
     * @throws InvalidArgumentException When the type cannot be generated.
     *
     * @return ColumnType A stored generated column of that type.
     */
    #[Override]
    public function generated(ColumnType $columnType, Term $term): ColumnType
    {
        if ($columnType->generatedAs instanceof Term) {
            throw new InvalidArgumentException('A generated column is generated once: its type is a plain column type.');
        }

        return new ColumnType($columnType->logicalType, $columnType->length, $columnType->scale, $columnType->choices, $term);
    }
}
