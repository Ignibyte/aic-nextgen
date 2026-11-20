<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain\Import;

use function array_any;
use function array_map;

/**
 * A table read from a schema dump (#318), with every fact the dump states about it.
 */
final readonly class ImportedTable
{
    /**
     * @param string                   $name        The table.
     * @param bool                     $unlogged    Whether it is unlogged.
     * @param list<ImportedColumn>     $columns     Its columns, in order.
     * @param list<string>             $primary     Its primary key's columns.
     * @param list<list<string>>       $uniques     Its unique constraints.
     * @param list<ImportedCheck>      $checks      Its checks.
     * @param list<ImportedIndex>      $indexes     Its indexes.
     * @param list<ImportedForeignKey> $foreignKeys Its foreign keys.
     */
    public function __construct(
        public string $name,
        public bool $unlogged,
        public array $columns,
        public array $primary = [],
        public array $uniques = [],
        public array $checks = [],
        public array $indexes = [],
        public array $foreignKeys = [],
    ) {
    }

    /**
     * @param string $name A column.
     *
     * @return ImportedColumn|null The column, or null when the table has none of that name.
     */
    public function column(string $name): ?ImportedColumn
    {
        foreach ($this->columns as $column) {
            if ($column->name === $name) {
                return $column;
            }
        }

        return null;
    }

    /**
     * @param ImportedColumn $importedColumn A column of the table, changed.
     *
     * @return self The table with it in place of the column of its name.
     */
    public function withColumn(ImportedColumn $importedColumn): self
    {
        $columns = array_map(
            static fn (ImportedColumn $column): ImportedColumn => $column->name === $importedColumn->name ? $importedColumn : $column,
            $this->columns,
        );

        return $this->with($columns, $this->primary, $this->uniques, $this->checks);
    }

    /**
     * @param list<string> $columns The primary key's columns.
     *
     * @return self The table with that primary key.
     */
    public function withPrimary(array $columns): self
    {
        return $this->with($this->columns, $columns, $this->uniques, $this->checks);
    }

    /**
     * @param list<string> $columns A unique constraint's columns.
     *
     * @return self The table with it.
     */
    public function withUnique(array $columns): self
    {
        return $this->with($this->columns, $this->primary, [...$this->uniques, $columns], $this->checks);
    }

    /**
     * @param list<ImportedCheck> $checks Its checks.
     *
     * @return self The table with those checks in place of its own.
     */
    public function withChecks(array $checks): self
    {
        return $this->with($this->columns, $this->primary, $this->uniques, $checks);
    }

    /**
     * @param ImportedIndex $importedIndex An index.
     *
     * @return self The table with it.
     */
    public function withIndex(ImportedIndex $importedIndex): self
    {
        return new self(
            $this->name,
            $this->unlogged,
            $this->columns,
            $this->primary,
            $this->uniques,
            $this->checks,
            [...$this->indexes, $importedIndex],
            $this->foreignKeys,
        );
    }

    /**
     * @param ImportedForeignKey $importedForeignKey A foreign key.
     *
     * @return self The table with it.
     */
    public function withForeignKey(ImportedForeignKey $importedForeignKey): self
    {
        return new self(
            $this->name,
            $this->unlogged,
            $this->columns,
            $this->primary,
            $this->uniques,
            $this->checks,
            $this->indexes,
            [...$this->foreignKeys, $importedForeignKey],
        );
    }

    /**
     * @return bool Whether an index needs the pg_trgm extension.
     */
    public function needsTrigrams(): bool
    {
        return array_any($this->indexes, static fn (ImportedIndex $importedIndex): bool => $importedIndex->method === 'Trigram');
    }

    /**
     * @param list<ImportedColumn> $columns The columns.
     * @param list<string>         $primary The primary key.
     * @param list<list<string>>   $uniques The unique constraints.
     * @param list<ImportedCheck>  $checks  The checks.
     *
     * @return self The table with them.
     */
    private function with(array $columns, array $primary, array $uniques, array $checks): self
    {
        return new self($this->name, $this->unlogged, $columns, $primary, $uniques, $checks, $this->indexes, $this->foreignKeys);
    }
}
