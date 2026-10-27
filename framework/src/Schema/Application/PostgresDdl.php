<?php

declare(strict_types=1);

namespace AIC\Schema\Application;

use AIC\Schema\Contracts\ColumnType;
use AIC\Schema\Contracts\LogicalType;
use AIC\Schema\Contracts\OnDelete;
use AIC\Schema\Domain\Alteration;
use AIC\Schema\Domain\Column;
use AIC\Schema\Domain\ColumnGroup;
use AIC\Schema\Domain\Constraints;
use AIC\Schema\Domain\ForeignKey;
use AIC\Schema\Domain\Identifier;
use AIC\Schema\Domain\Retype;
use AIC\Schema\Domain\Table;
use AIC\Schema\Domain\View;
use InvalidArgumentException;

use function array_merge;
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
     * @return string Its `CREATE TABLE`, with its primary key, unique constraints and foreign keys.
     */
    public function createTable(Table $table): string
    {
        $definitions = $this->columns($table);

        if ($table->primaryKey !== []) {
            $definitions[] = 'PRIMARY KEY (' . implode(', ', $table->primaryKey) . ')';
        }

        $definitions = array_merge($definitions, $this->clauses($table->constraints));

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
        return $this->indexes($table->name, $table->constraints);
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

        foreach ($this->clauses($alteration->constraints) as $clause) {
            $statements[] = $table . ' ADD ' . $clause;
        }

        return array_merge($statements, $this->indexes($alteration->table, $alteration->constraints));
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
     * @param Constraints $constraints A table's constraints.
     *
     * @return list<string> Its unique constraints and then its foreign keys, as table constraints.
     */
    private function clauses(Constraints $constraints): array
    {
        return array_merge($this->uniques($constraints), $this->foreignKeys($constraints));
    }

    /**
     * @param Constraints $constraints A table's constraints.
     *
     * @return list<string> Its unique constraints.
     */
    private function uniques(Constraints $constraints): array
    {
        $definitions = [];

        foreach ($constraints->uniques as $unique) {
            if (!($unique instanceof ColumnGroup)) {
                continue;
            }

            $definitions[] = 'UNIQUE (' . implode(', ', $unique->columns) . ')';
        }

        return $definitions;
    }

    /**
     * @param Constraints $constraints A table's constraints.
     *
     * @return list<string> Its foreign-key constraints.
     */
    private function foreignKeys(Constraints $constraints): array
    {
        $definitions = [];

        foreach ($constraints->foreignKeys as $foreignKey) {
            if (!($foreignKey instanceof ForeignKey)) {
                continue;
            }

            $definitions[] = $this->foreignKey($foreignKey);
        }

        return $definitions;
    }

    /**
     * @param Identifier  $identifier  The table.
     * @param Constraints $constraints Its constraints.
     *
     * @throws InvalidArgumentException When an index name would be longer than an identifier may be.
     *
     * @return list<string> A `CREATE INDEX` for each of its indexes, in order.
     */
    private function indexes(Identifier $identifier, Constraints $constraints): array
    {
        $statements = [];

        foreach ($constraints->indexes as $index) {
            if (!($index instanceof ColumnGroup)) {
                continue;
            }

            $statements[] = 'CREATE INDEX ' . $index->indexName($identifier)->value . ' ON ' . $identifier->value
                . ' (' . implode(', ', $index->columns) . ')';
        }

        return $statements;
    }

    /**
     * @param ForeignKey $foreignKey A foreign key.
     *
     * @return string Its table constraint.
     */
    private function foreignKey(ForeignKey $foreignKey): string
    {
        $onDelete = match ($foreignKey->onDelete) {
            OnDelete::Restrict => 'RESTRICT',
            OnDelete::Cascade => 'CASCADE',
            OnDelete::SetNull => 'SET NULL',
        };

        return 'FOREIGN KEY (' . $foreignKey->column->value . ') REFERENCES ' . $foreignKey->table->value
            . ' (' . $foreignKey->foreignColumn->value . ') ON DELETE ' . $onDelete;
    }

    /**
     * @param Column $column A column.
     *
     * @return string Its definition.
     */
    private function column(Column $column): string
    {
        return $column->name->value . ' ' . $this->type($column->type) . ($column->nullable ? '' : ' NOT NULL');
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
        };
    }
}
