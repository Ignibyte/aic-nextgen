<?php

declare(strict_types=1);

namespace App\Ledger\Service;

use AIC\Database\Contracts\QueryException;
use AIC\Entity\Application\EntityNotFoundException;
use AIC\Entity\Application\EntityStore;
use AIC\Entity\Application\EntityTrees;
use App\Ledger\Entity\LedgerTypes;
use InvalidArgumentException;

use function array_reverse;

/**
 * GET /ledger/categories' work (#311): a tree of four categories, read
 * through the query forms (LedgerCategoryQueries, LedgerDerivedQueries) and walked down from its
 * root and up from a leaf. It deletes the tree, so it leaves the table as it
 * found it.
 */
final readonly class LedgerCategories
{
    /**
     * @param EntityStore           $entityStore           The entity store.
     * @param EntityTrees           $entityTrees           Walks the tree.
     * @param LedgerCategoryQueries $ledgerCategoryQueries Reads it through the query forms.
     * @param LedgerDerivedQueries  $ledgerDerivedQueries  Reads it through queries over queries (#329).
     * @param LedgerTypes           $ledgerTypes           The ledger's entity types.
     */
    public function __construct(
        private EntityStore $entityStore,
        private EntityTrees $entityTrees,
        private LedgerCategoryQueries $ledgerCategoryQueries,
        private LedgerDerivedQueries $ledgerDerivedQueries,
        private LedgerTypes $ledgerTypes,
    ) {
    }

    /**
     * @throws InvalidArgumentException When a value or a query is declared wrongly.
     * @throws EntityNotFoundException  When a category is gone before it is deleted.
     * @throws QueryException           When a write or a query fails.
     *
     * @return LedgerLines Each form and what it read.
     */
    public function body(): LedgerLines
    {
        $categories = $this->ledgerTypes->categories();
        $root = $this->category('Assets', null, '{"kind": "asset", "tags": []}');
        $cash = $this->category('Cash', $root, '{"kind": "asset", "tags": ["cash"]}');
        $bank = $this->category('Bank', $root, '{"kind": "asset", "tags": ["bank"]}');
        $petty = $this->category('Petty cash', $cash, '{"kind": "float", "tags": ["cash"]}');
        $keys = [$root, $cash, $bank, $petty];

        try {
            $lines = [...$this->ledgerCategoryQueries->computed($keys), ...$this->ledgerCategoryQueries->matched($keys)];

            $derived = [...$this->ledgerDerivedQueries->computed($keys), ...$this->ledgerDerivedQueries->joined($keys)];

            return new LedgerLines('steps', [...$lines, ...$derived, ...$this->walked($root, $petty)]);
        } finally {
            foreach (array_reverse($keys) as $key) {
                $this->entityStore->delete($categories, $key);
            }
        }
    }

    /**
     * @param int $root  The root's key.
     * @param int $petty The leaf's key.
     *
     * @throws InvalidArgumentException When the tree's field is declared wrongly.
     * @throws QueryException           When a walk fails.
     *
     * @return list<string> The tree down from the root and up from the leaf, each with its depth.
     */
    private function walked(int $root, int $petty): array
    {
        $categories = $this->ledgerTypes->categories();
        $lines = [];

        foreach ($this->entityTrees->descendants($categories, 'parent_id', $root) as $row) {
            $lines[] = 'down ' . $row->int('depth') . ': ' . $row->string('name');
        }

        foreach ($this->entityTrees->ancestors($categories, 'parent_id', $petty) as $row) {
            $lines[] = 'up ' . $row->int('depth') . ': ' . $row->string('name');
        }

        return $lines;
    }

    /**
     * @param string   $name   Its name.
     * @param int|null $parent Its parent's key; null for a root.
     * @param string   $traits Its traits, a JSON document.
     *
     * @throws InvalidArgumentException When a value does not fit.
     * @throws QueryException           When the insert fails.
     *
     * @return int Its key.
     */
    private function category(string $name, ?int $parent, string $traits): int
    {
        return $this->entityStore->insert($this->ledgerTypes->categories(), ['name' => $name, 'parent_id' => $parent, 'traits' => $traits]);
    }
}
