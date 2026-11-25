<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain\Make;

/**
 * One column of a table as its creating migration declares it (#338): its
 * name, its type as the Schema builder names it (`identity`, `uuidKey`,
 * `string`, `text`, `integer`, `bigInteger`, `timestampTz`, `date`,
 * `numeric`, `boolean`, `uuid`, `jsonb` or `choice`), whether it is nullable,
 * a choice's values, the table a foreign key on it references, and whether
 * the database generates it.
 */
final readonly class MigratedColumn
{
    /**
     * @param string       $name       Its name.
     * @param string       $type       Its builder type.
     * @param bool         $nullable   Whether it may be null.
     * @param list<string> $choices    A choice's values; empty for every other type.
     * @param string       $references The table its foreign key references; empty when it has none.
     * @param bool         $generated  Whether it is a stored generated column.
     */
    public function __construct(
        public string $name,
        public string $type,
        public bool $nullable = false,
        public array $choices = [],
        public string $references = '',
        public bool $generated = false,
    ) {
    }

    /**
     * @return self It, nullable.
     */
    public function asNullable(): self
    {
        return new self($this->name, $this->type, true, $this->choices, $this->references, $this->generated);
    }

    /**
     * @param string $table The table its foreign key references.
     *
     * @return self It, referencing that table.
     */
    public function referencing(string $table): self
    {
        return new self($this->name, $this->type, $this->nullable, $this->choices, $table, $this->generated);
    }
}
