<?php

declare(strict_types=1);

namespace AIC\Schema\Domain;

use AIC\Schema\Contracts\OnDelete;
use InvalidArgumentException;

use function implode;
use function in_array;

/**
 * A table to create: its name, its columns in order, its primary key, and its
 * unique constraints, indexes and foreign keys.
 */
final readonly class Table
{
    /**
     * @param Identifier   $name        The table's name.
     * @param list<object> $columns     Its Columns, in order: objects, the list form elephc keeps (plan.md).
     * @param list<string> $primaryKey  The primary key's columns; empty for none.
     * @param list<object> $uniques     Its unique constraints, ColumnGroups.
     * @param list<object> $indexes     Its indexes, ColumnGroups.
     * @param list<object> $foreignKeys Its ForeignKeys.
     *
     * @throws InvalidArgumentException When there are no columns, a key, constraint or index names a column
     *                                  the table lacks, or a SetNull foreign key's column is required.
     */
    public function __construct(
        public Identifier $name,
        public array $columns,
        public array $primaryKey,
        public array $uniques,
        public array $indexes,
        public array $foreignKeys,
    ) {
        $names = self::names($columns);

        if ($names === []) {
            throw new InvalidArgumentException('Table ' . $name->value . ': a table needs a column.');
        }

        $this->check('the primary key', $primaryKey, $names);
        $this->checkGroups('a unique constraint', $uniques, $names);
        $this->checkGroups('an index', $indexes, $names);
        $this->checkForeignKeys($names);
    }

    /**
     * @param ColumnGroup $columnGroup One of the table's indexes.
     *
     * @throws InvalidArgumentException When the name would be longer than an identifier may be.
     *
     * @return Identifier Its name: the table, the columns, then `idx`, joined by underscores.
     */
    public function indexName(ColumnGroup $columnGroup): Identifier
    {
        return new Identifier($this->name->value . '_' . implode('_', $columnGroup->columns) . '_idx');
    }

    /**
     * @param string       $what   What names the columns, for the message.
     * @param list<object> $groups ColumnGroups.
     * @param list<string> $names  The table's column names.
     *
     * @throws InvalidArgumentException When a group names a column the table lacks.
     */
    private function checkGroups(string $what, array $groups, array $names): void
    {
        foreach ($groups as $group) {
            if (!$group instanceof ColumnGroup) {
                continue;
            }

            $this->check($what, $group->columns, $names);
        }
    }

    /**
     * @param list<string> $names The table's column names.
     *
     * @throws InvalidArgumentException When a foreign key's column is missing, or is required under SetNull.
     */
    private function checkForeignKeys(array $names): void
    {
        foreach ($this->foreignKeys as $foreignKey) {
            if (!$foreignKey instanceof ForeignKey) {
                continue;
            }

            $this->check('a foreign key', [$foreignKey->column->value], $names);
            $this->checkSetNull($foreignKey);
        }
    }

    /**
     * @param ForeignKey $foreignKey One of the table's foreign keys, its column present.
     *
     * @throws InvalidArgumentException When it sets its column null and the column is required.
     */
    private function checkSetNull(ForeignKey $foreignKey): void
    {
        $column = $foreignKey->column->value;

        if ($foreignKey->onDelete === OnDelete::SetNull && !$this->nullable($column)) {
            throw new InvalidArgumentException(
                'Table ' . $this->name->value . ': ' . $column . ' is required, so its foreign key cannot set it null.',
            );
        }
    }

    /**
     * @param string       $what    What names the columns, for the message.
     * @param list<string> $columns The columns it names.
     * @param list<string> $names   The table's column names.
     *
     * @throws InvalidArgumentException When it names a column the table lacks.
     */
    private function check(string $what, array $columns, array $names): void
    {
        $missing = self::missing($columns, $names);

        if ($missing !== '') {
            throw new InvalidArgumentException('Table ' . $this->name->value . ': ' . $what . ' names ' . $missing . ' (no such column).');
        }
    }

    /**
     * @param string $name A column of the table.
     *
     * @return bool Whether it may hold null.
     */
    private function nullable(string $name): bool
    {
        foreach ($this->columns as $column) {
            if ($column instanceof Column && $column->name->value === $name) {
                return $column->nullable;
            }
        }

        return false;
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
     * @param list<string> $columns The columns named.
     * @param list<string> $names   The table's column names.
     *
     * @return string The first column named that the table lacks; empty when it has them all.
     */
    private static function missing(array $columns, array $names): string
    {
        foreach ($columns as $column) {
            if (!in_array($column, $names, true)) {
                return $column;
            }
        }

        return '';
    }
}
