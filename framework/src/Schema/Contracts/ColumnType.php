<?php

declare(strict_types=1);

namespace AIC\Schema\Contracts;

use InvalidArgumentException;

use function array_unique;
use function count;
use function in_array;
use function preg_match;

/**
 * A column's type, as a migration names it: shared by the builder that
 * creates a table and the alteration that adds a column or changes one's
 * type (#86). A migration takes it from `Schema::types()`.
 */
final readonly class ColumnType
{
    /**
     * @param LogicalType  $logicalType The type.
     * @param int          $length      A String's longest value or a Numeric's precision; 0 for the other types.
     * @param int          $scale       A Numeric's digits after the point; 0 for the other types.
     * @param list<string> $choices     A Choice's values (#307), each 1 to 63 letters, digits, `_`, `.`, `:` or `-`,
     *                                  none twice; empty for the other types.
     * @param Term|null    $generatedAs What a generated column holds (#310), from `ColumnTypes::generated()`;
     *                                  null for a column the app writes.
     * @param Term|null    $defaultTo   What a column holds when a row names none (#308), from
     *                                  `ColumnTypes::defaulted()`; null for no default.
     *
     * @throws InvalidArgumentException When the length, the scale or the choices do not fit the type, a key or
     *                                  a choice is generated, or a key or a generated column has a default.
     */
    public function __construct(
        public LogicalType $logicalType,
        public int $length,
        public int $scale,
        public array $choices = [],
        public ?Term $generatedAs = null,
        public ?Term $defaultTo = null,
    ) {
        $this->checkComputed();

        if (!$this->fits() || !$this->chosen()) {
            throw new InvalidArgumentException(match ($logicalType) {
                LogicalType::String => 'A string column has a positive length.',
                LogicalType::Numeric => 'A numeric column takes a precision of 1 to 1000 and a scale of 0 to the precision.',
                LogicalType::Choice => 'A choice column takes one or more distinct values of 1 to 63 letters, digits, _, ., : or -.',
                LogicalType::BigIdentity, LogicalType::Text, LogicalType::TimestampTz, LogicalType::Integer,
                LogicalType::BigInteger, LogicalType::Date, LogicalType::Boolean, LogicalType::Uuid, LogicalType::UuidKey,
                LogicalType::Jsonb => 'Only a string or a numeric column has a size, and only a choice column has values.',
            });
        }
    }

    /**
     * @throws InvalidArgumentException When a key or a choice is generated, or a key or a generated column has a
     *                                  default.
     */
    private function checkComputed(): void
    {
        if ($this->generatedAs instanceof Term && !$this->generatable()) {
            throw new InvalidArgumentException('A key or a choice column cannot be generated.');
        }

        if ($this->defaultTo instanceof Term && ($this->generatedAs instanceof Term || !$this->defaultable())) {
            throw new InvalidArgumentException('A key or a generated column has no default of its own.');
        }
    }

    /**
     * @return bool Whether a column of this type may be generated: any but a key or a choice.
     */
    private function generatable(): bool
    {
        return !in_array($this->logicalType, [LogicalType::BigIdentity, LogicalType::UuidKey, LogicalType::Choice], true);
    }

    /**
     * @return bool Whether a column of this type may take a default: any but a key, which has its own.
     */
    private function defaultable(): bool
    {
        return $this->logicalType !== LogicalType::BigIdentity && $this->logicalType !== LogicalType::UuidKey;
    }

    /**
     * @return bool Whether a Choice has distinct, well-formed values and any other type has none.
     */
    private function chosen(): bool
    {
        if ($this->logicalType !== LogicalType::Choice) {
            return $this->choices === [];
        }

        foreach ($this->choices as $choice) {
            if (preg_match('/^[A-Za-z0-9_.:-]{1,63}$/', $choice) !== 1) {
                return false;
            }
        }

        return $this->choices !== [] && count(array_unique($this->choices)) === count($this->choices);
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
            LogicalType::BigInteger, LogicalType::Date, LogicalType::Boolean, LogicalType::Uuid, LogicalType::UuidKey,
            LogicalType::Jsonb, LogicalType::Choice => $this->length === 0 && $this->scale === 0,
        };
    }
}
