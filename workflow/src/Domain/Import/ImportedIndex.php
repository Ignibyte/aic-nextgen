<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain\Import;

/**
 * An index of an imported table (#318).
 */
final readonly class ImportedIndex
{
    /**
     * @param list<string>  $columns The columns.
     * @param string        $method  The `IndexMethod` case; empty for a btree.
     * @param bool          $unique  Whether it is unique.
     * @param CodeCall|null $where   The rows it covers, for a partial index.
     */
    public function __construct(
        public array $columns,
        public string $method,
        public bool $unique,
        public ?CodeCall $where,
    ) {
    }
}
