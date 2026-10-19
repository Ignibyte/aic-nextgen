<?php

declare(strict_types=1);

namespace AIC\Database\Contracts;

use function array_key_exists;
use function is_string;

/**
 * One result row, its values narrowed to scalars or null when it was read.
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
}
