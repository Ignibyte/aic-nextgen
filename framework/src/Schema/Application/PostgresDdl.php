<?php

declare(strict_types=1);

namespace AIC\Schema\Application;

use AIC\Schema\Contracts\ColumnType;
use AIC\Schema\Contracts\LogicalType;
use AIC\Schema\Contracts\Term;
use AIC\Schema\Domain\Alteration;
use AIC\Schema\Domain\Column;
use AIC\Schema\Domain\Identifier;
use AIC\Schema\Domain\Retype;
use AIC\Schema\Domain\Table;
use AIC\Schema\Domain\TableGuard;
use AIC\Schema\Domain\View;
use InvalidArgumentException;

use function array_merge;
use function implode;

/**
 * Postgres DDL for the schema AST; indexes and guards through PostgresIndexDdl
 * and PostgresGuardDdl (#310).
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
     * @param View $view A view.
     *
     * @return string Its `CREATE OR REPLACE VIEW`.
     */
    public function createView(View $view): string
    {
        return 'CREATE OR REPLACE VIEW ' . $view->identifier->value . ' AS ' . $view->select;
    }

    /**
     * @param Table $table A table.
     *
     * @throws InvalidArgumentException When a check's name would be longer than an identifier may be.
     *
     * @return string Its `CREATE TABLE`, with its primary key, unique constraints, foreign keys and checks.
     */
    public function createTable(Table $table): string
    {
        $definitions = $this->columns($table);

        if ($table->primaryKey !== []) {
            $definitions[] = 'PRIMARY KEY (' . implode(', ', $table->primaryKey) . ')';
        }

        $definitions = array_merge($definitions, (new PostgresClauseDdl())->clauses($table->name, $table->constraints));

        return 'CREATE TABLE ' . $table->name->value . ' (' . implode(', ', $definitions) . ')';
    }

    /**
     * @param Table $table A table.
     *
     * @throws InvalidArgumentException When an index name would be longer than an identifier may be.
     *
     * @return list<string> A `CREATE INDEX` for each of its indexes, in order.
     */
    public function createIndexes(Table $table): array
    {
        return (new PostgresIndexDdl())->indexes($table->name, $table->constraints);
    }

    /**
     * @param TableGuard $tableGuard A guard on a table (#310).
     *
     * @throws InvalidArgumentException When a function or trigger name would be longer than an identifier may be.
     *
     * @return list<string> Its function and triggers.
     */
    public function createGuard(TableGuard $tableGuard): array
    {
        return (new PostgresGuardDdl())->statements($tableGuard);
    }

    /**
     * @param Alteration $alteration Changes to an existing table.
     *
     * @throws InvalidArgumentException When an index name would be longer than an identifier may be.
     *
     * @return list<string> An `ALTER TABLE` for each column added, then for each type change, then for each
     *                      constraint, then a `CREATE INDEX` for each index (#86).
     */
    public function alterTable(Alteration $alteration): array
    {
        $table = 'ALTER TABLE ' . $alteration->table->value;
        $statements = [];

        foreach ($alteration->added as $column) {
            if (!($column instanceof Column)) {
                continue;
            }

            $statements[] = $table . ' ADD COLUMN ' . $this->column($column);
        }

        $statements = array_merge($statements, $this->retypes($table, $alteration->retyped));

        foreach ((new PostgresClauseDdl())->clauses($alteration->table, $alteration->constraints) as $clause) {
            $statements[] = $table . ' ADD ' . $clause;
        }

        return array_merge($statements, (new PostgresIndexDdl())->indexes($alteration->table, $alteration->constraints));
    }

    /**
     * @param string       $table   The `ALTER TABLE` the statements start with.
     * @param list<object> $retyped Retypes, in order.
     *
     * @return list<string> An `ALTER COLUMN ... TYPE` for each, with its `USING` when it has one.
     */
    private function retypes(string $table, array $retyped): array
    {
        $statements = [];

        foreach ($retyped as $retype) {
            if (!($retype instanceof Retype)) {
                continue;
            }

            $using = $retype->using === ''
                ? ''
                : ' USING ' . $retype->using;
            $statements[] = $table . ' ALTER COLUMN ' . $retype->column->value . ' TYPE ' . $this->type($retype->type) . $using;
        }

        return $statements;
    }

    /**
     * @param Table $table A table.
     *
     * @return list<string> Its column definitions, in order.
     */
    private function columns(Table $table): array
    {
        $definitions = [];

        foreach ($table->columns as $column) {
            if (!($column instanceof Column)) {
                continue;
            }

            $definitions[] = $this->column($column);
        }

        return $definitions;
    }

    /**
     * @param Column $column A column.
     *
     * @return string Its definition: its default after the type (#308), a generated column's expression last (#310).
     */
    private function column(Column $column): string
    {
        $generatedAs = $column->type->generatedAs;
        $defaultTo = $column->type->defaultTo;
        $generated = $generatedAs instanceof Term
            ? ' GENERATED ALWAYS AS (' . $generatedAs->sql . ') STORED'
            : '';
        $default = $defaultTo instanceof Term
            ? ' DEFAULT ' . $defaultTo->sql
            : '';

        return $column->name->value . ' ' . $this->type($column->type) . $default . ($column->nullable ? '' : ' NOT NULL')
            . $this->check($column) . $generated;
    }

    /**
     * @param ColumnType $columnType A column's type.
     *
     * @return string Its Postgres name.
     */
    private function type(ColumnType $columnType): string
    {
        return match ($columnType->logicalType) {
            LogicalType::BigIdentity => 'bigint GENERATED ALWAYS AS IDENTITY',
            LogicalType::String => 'varchar(' . $columnType->length . ')',
            LogicalType::Text => 'text',
            LogicalType::TimestampTz => 'timestamptz',
            LogicalType::Integer => 'integer',
            LogicalType::BigInteger => 'bigint',
            LogicalType::Date => 'date',
            LogicalType::Numeric => 'numeric(' . $columnType->length . ', ' . $columnType->scale . ')',
            LogicalType::Boolean => 'boolean',
            LogicalType::Uuid => 'uuid',
            LogicalType::UuidKey => 'uuid DEFAULT gen_random_uuid()',
            LogicalType::Jsonb => 'jsonb',
            LogicalType::Choice => 'text',
        };
    }

    /**
     * @param Column $column A column.
     *
     * @return string A choice's `CHECK (column IN (...))` (#307); empty for any other type. Each value is checked
     *                to letters, digits and `_.:-`, so it needs no escaping.
     */
    private function check(Column $column): string
    {
        $choices = $column->type->choices;

        return $choices === []
            ? ''
            : ' CHECK (' . $column->name->value . " IN ('" . implode("', '", $choices) . "'))";
    }
}
