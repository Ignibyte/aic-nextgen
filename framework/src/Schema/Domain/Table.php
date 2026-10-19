<?php

declare(strict_types=1);

namespace AIC\Schema\Domain;

use InvalidArgumentException;

use function in_array;

/**
 * A table to create: its name, its columns in order and its primary key.
 */
final readonly class Table
{
    /**
     * @param Identifier   $name       The table's name.
     * @param list<object> $columns    Its Columns, in order: objects, the list form elephc keeps (plan.md).
     * @param list<string> $primaryKey The primary key's columns; empty for none.
     *
     * @throws InvalidArgumentException When there are no columns, or the key names a column the table lacks.
     */
    public function __construct(
        public Identifier $name,
        public array $columns,
        public array $primaryKey,
    ) {
        $names = self::names($columns);

        if ($names === []) {
            throw new InvalidArgumentException('Table ' . $name->value . ': a table needs a column.');
        }

        $missing = self::missing($primaryKey, $names);

        if ($missing !== '') {
            throw new InvalidArgumentException('Table ' . $name->value . ': the primary key names ' . $missing . ' (no such column).');
        }
    }

    /**
     * @param list<object> $columns The Columns.
     *
     * @return list<string> Their names, in order.
     */
    private static function names(array $columns): array
    {
        $names = [];

        foreach ($columns as $column) {
            if (!$column instanceof Column) {
                continue;
            }

            $names[] = $column->name->value;
        }

        return $names;
    }

    /**
     * @param list<string> $primaryKey The primary key's columns.
     * @param list<string> $names      The table's column names.
     *
     * @return string The first key column the table lacks; empty when it has them all.
     */
    private static function missing(array $primaryKey, array $names): string
    {
        foreach ($primaryKey as $column) {
            if (!in_array($column, $names, true)) {
                return $column;
            }
        }

        return '';
    }
}
