<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain\Import;

/**
 * A foreign key of an imported table (#318).
 */
final readonly class ImportedForeignKey
{
    /**
     * @param string $column        The column.
     * @param string $table         The table it references.
     * @param string $foreignColumn The column there.
     * @param string $onDelete      The `OnDelete` case.
     */
    public function __construct(
        public string $column,
        public string $table,
        public string $foreignColumn,
        public string $onDelete,
    ) {
    }
}
