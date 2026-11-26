<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain\Import;

/**
 * A foreign key taken off its table's create because it closes a reference cycle (#349): the migration of the table
 * it references, made later, adds it to the table that holds it.
 */
final readonly class DeferredForeignKey
{
    /**
     * @param string             $table              The table that holds it.
     * @param ImportedForeignKey $importedForeignKey The key.
     */
    public function __construct(
        public string $table,
        public ImportedForeignKey $importedForeignKey,
    ) {
    }
}
