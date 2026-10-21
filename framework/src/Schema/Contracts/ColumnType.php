<?php

declare(strict_types=1);

namespace AIC\Schema\Contracts;

use InvalidArgumentException;

/**
 * A column's type, as a migration names it: shared by the builder that
 * creates a table and the alteration that adds a column or changes one's
 * type (#86). A migration takes it from `Schema::types()`.
 */
final readonly class ColumnType
{
    /**
     * @param LogicalType $logicalType The type.
     * @param int         $length      A String's longest value or a Numeric's precision; 0 for the other types.
     * @param int         $scale       A Numeric's digits after the point; 0 for the other types.
     *
     * @throws InvalidArgumentException When the length or the scale does not fit the type.
     */
    public function __construct(
        public LogicalType $logicalType,
        public int $length,
        public int $scale,
    ) {
        if (!$this->fits()) {
            throw new InvalidArgumentException(match ($logicalType) {
                LogicalType::String => 'A string column has a positive length.',
                LogicalType::Numeric => 'A numeric column takes a precision of 1 to 1000 and a scale of 0 to the precision.',
                LogicalType::BigIdentity, LogicalType::Text, LogicalType::TimestampTz, LogicalType::Integer,
                LogicalType::BigInteger, LogicalType::Date, LogicalType::Boolean => 'Only a string or a numeric column has a size.',
            });
        }
    }

    /**
     * @return bool Whether the length and the scale fit the type.
     */
    private function fits(): bool
    {
        return match ($this->logicalType) {
            LogicalType::String => $this->length >= 1 && $this->scale === 0,
            LogicalType::Numeric => $this->length >= 1 && $this->length <= 1_000 && $this->scale >= 0 && $this->scale <= $this->length,
            LogicalType::BigIdentity, LogicalType::Text, LogicalType::TimestampTz, LogicalType::Integer,
            LogicalType::BigInteger, LogicalType::Date, LogicalType::Boolean => $this->length === 0 && $this->scale === 0,
        };
    }
}
