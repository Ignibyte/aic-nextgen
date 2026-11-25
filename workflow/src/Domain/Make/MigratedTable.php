<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain\Make;

/**
 * A table as its creating migration declares it (#338): its name, its columns
 * in order, its primary key's columns, and the migration it was read from.
 */
final readonly class MigratedTable
{
    /**
     * @param string               $table   Its name.
     * @param list<MigratedColumn> $columns Its columns, in order.
     * @param list<string>         $primary Its primary key's columns; empty when the migration names none.
     * @param string               $file    The migration it was read from, relative to the project.
     */
    public function __construct(
        public string $table,
        public array $columns,
        public array $primary,
        public string $file,
    ) {
    }
}
