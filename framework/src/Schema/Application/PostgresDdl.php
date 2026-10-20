<?php

declare(strict_types=1);

namespace AIC\Schema\Application;

use AIC\Schema\Domain\Column;
use AIC\Schema\Domain\Identifier;
use AIC\Schema\Domain\LogicalType;
use AIC\Schema\Domain\Table;

use function implode;

/**
 * Postgres DDL for the schema AST.
 */
final readonly class PostgresDdl
{
    /**
     * @param Identifier $identifier An extension's name.
     *
     * @return string Its `CREATE EXTENSION IF NOT EXISTS`.
     */
    public function createExtension(Identifier $identifier): string
    {
        return 'CREATE EXTENSION IF NOT EXISTS ' . $identifier->value;
    }

    /**
     * @param Table $table A table.
     *
     * @return string Its `CREATE TABLE`.
     */
    public function createTable(Table $table): string
    {
        $definitions = [];

        foreach ($table->columns as $column) {
            if (!($column instanceof Column)) {
                continue;
            }

            $definitions[] = $this->column($column);
        }

        if ($table->primaryKey !== []) {
            $definitions[] = 'PRIMARY KEY (' . implode(', ', $table->primaryKey) . ')';
        }

        return 'CREATE TABLE ' . $table->name->value . ' (' . implode(', ', $definitions) . ')';
    }

    /**
     * @param Column $column A column.
     *
     * @return string Its definition.
     */
    private function column(Column $column): string
    {
        $type = match ($column->type) {
            LogicalType::BigIdentity => 'bigint GENERATED ALWAYS AS IDENTITY',
            LogicalType::String => 'varchar(' . $column->length . ')',
            LogicalType::Text => 'text',
            LogicalType::TimestampTz => 'timestamptz',
            LogicalType::Integer => 'integer',
        };

        return $column->name->value . ' ' . $type . ($column->nullable ? '' : ' NOT NULL');
    }
}
