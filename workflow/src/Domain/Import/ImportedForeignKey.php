<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain\Import;

/**
 * A foreign key of an imported table (#318), over one column or several (#344).
 */
final readonly class ImportedForeignKey
{
    /**
     * @param list<string> $columns        Its columns, in order.
     * @param string       $table          The table it references.
     * @param list<string> $foreignColumns The columns there, in the same order.
     * @param string       $onDelete       The `OnDelete` case.
     */
    public function __construct(
        public array $columns,
        public string $table,
        public array $foreignColumns,
        public string $onDelete,
    ) {
    }
}
