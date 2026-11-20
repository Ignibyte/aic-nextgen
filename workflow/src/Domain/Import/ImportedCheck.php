<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain\Import;

/**
 * A check of an imported table (#318).
 */
final readonly class ImportedCheck
{
    /**
     * @param string   $name      The constraint's name without the table's prefix, as `check()` takes it.
     * @param CodeCall $predicate What it holds.
     */
    public function __construct(
        public string $name,
        public CodeCall $predicate,
    ) {
    }
}
