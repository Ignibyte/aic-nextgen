<?php

declare(strict_types=1);

namespace AIC\Database\Contracts;

use DateTimeImmutable;
use Throwable;

use function array_key_exists;
use function is_bool;
use function is_int;
use function is_string;

/**
 * One result row, its values narrowed to scalars or null when it was read. A
 * `date` reads as a `YYYY-MM-DD` string and a `numeric` as its decimal string,
 * under PHP and natively alike; a `timestamptz` reads as its text, or through
 * `dateTime()` as a `DateTimeImmutable` (#307).
 */
final readonly class Row
{
    /**
     * @param array<string, string|int|float|bool|null> $values The values by column name.
     */
    public function __construct(
        private array $values,
    ) {
    }

    /**
     * @param string $column The column name.
     *
     * @throws QueryException When the column is missing or not a string.
     *
     * @return string Its value.
     */
    public function string(string $column): string
    {
        $value = array_key_exists($column, $this->values)
            ? $this->values[$column]
            : null;

        if (!is_string($value)) {
            throw new QueryException('The row has no string column ' . $column . '.');
        }

        return $value;
    }

    /**
     * @param string $column The column name.
     *
     * @throws QueryException When the column is missing or not an integer.
     *
     * @return int Its value.
     */
    public function int(string $column): int
    {
        $value = array_key_exists($column, $this->values)
            ? $this->values[$column]
            : null;

        if (!is_int($value)) {
            throw new QueryException('The row has no integer column ' . $column . '.');
        }

        return $value;
    }

    /**
     * @param string $column The column name.
     *
     * @throws QueryException When the column is missing or not a boolean.
     *
     * @return bool Its value.
     */
    public function bool(string $column): bool
    {
        $value = array_key_exists($column, $this->values)
            ? $this->values[$column]
            : null;

        if (!is_bool($value)) {
            throw new QueryException('The row has no boolean column ' . $column . '.');
        }

        return $value;
    }

    /**
     * @param string $column The column name.
     *
     * @throws QueryException When the column is missing, or neither a string nor null.
     *
     * @return string|null Its value; null when the database holds none (#184).
     */
    public function stringOrNull(string $column): ?string
    {
        if (!array_key_exists($column, $this->values)) {
            throw new QueryException('The row has no column ' . $column . '.');
        }

        return $this->values[$column] === null
            ? null
            : $this->string($column);
    }

    /**
     * @param string $column The column name.
     *
     * @throws QueryException When the column is missing, or neither an integer nor null.
     *
     * @return int|null Its value; null when the database holds none (#184).
     */
    public function intOrNull(string $column): ?int
    {
        if (!array_key_exists($column, $this->values)) {
            throw new QueryException('The row has no column ' . $column . '.');
        }

        return $this->values[$column] === null
            ? null
            : $this->int($column);
    }

    /**
     * @param string $column A `timestamptz` column's name.
     *
     * @throws QueryException When the column is missing, null or not a point in time.
     *
     * @return DateTimeImmutable Its value, in the offset the database gave (#307), as the compiled site reads it too
     *                           while the session's zone is UTC, Postgres's default.
     */
    public function dateTime(string $column): DateTimeImmutable
    {
        return $this->dateTimeOrNull($column) ?? throw new QueryException('The row has no point in time in column ' . $column . '.');
    }

    /**
     * @param string $column A nullable `timestamptz` column's name.
     *
     * @throws QueryException When the column is missing, or holds text that is not a point in time.
     *
     * @return DateTimeImmutable|null Its value; null when it is null.
     */
    public function dateTimeOrNull(string $column): ?DateTimeImmutable
    {
        $text = $this->stringOrNull($column);

        if ($text === null) {
            return null;
        }

        try {
            return new DateTimeImmutable($text);
        } catch (Throwable $throwable) {
            throw new QueryException("The row's column " . $column . ' is not a point in time.', 0, $throwable);
        }
    }
}
