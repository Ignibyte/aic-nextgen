<?php

declare(strict_types=1);

namespace App\Ledger\Service;

use AIC\Database\Contracts\QueryException;
use AIC\Entity\Domain\Comparison;
use AIC\Entity\Domain\Condition;
use App\Ledger\Entity\LedgerTypes;
use InvalidArgumentException;

/**
 * The query forms of #311 over the ledger's category tree, by name, never by
 * key: computed columns (a rank among siblings, a JSON trait, a likeness)
 * and the comparisons and subquery that keep rows.
 */
final readonly class LedgerCategoryQueries
{
    /**
     * @param LedgerTree  $ledgerTree  Queries the tree's rows.
     * @param LedgerTypes $ledgerTypes The ledger's entity types.
     */
    public function __construct(
        private LedgerTree $ledgerTree,
        private LedgerTypes $ledgerTypes,
    ) {
    }

    /**
     * @param list<int> $keys The tree's keys.
     *
     * @throws InvalidArgumentException When a column is declared wrongly.
     * @throws QueryException           When the query fails.
     *
     * @return list<string> Each category's rank among its siblings by name, its kind and its likeness to "cash".
     */
    public function computed(array $keys): array
    {
        $rows = $this->ledgerTree->ranked($keys)->orderBy('name')->rows();
        $lines = [];

        foreach ($rows as $row) {
            $lines[] = $row->string('name') . ': rank ' . $row->int('sibling_rank') . ', kind ' . $row->string('kind')
                . ', likeness ' . $row->string('likeness');
        }

        return $lines;
    }

    /**
     * @param list<int> $keys The tree's keys.
     *
     * @throws InvalidArgumentException When a condition is declared wrongly.
     * @throws QueryException           When a query fails.
     *
     * @return list<string> The names each comparison keeps, and the parents of "Petty cash".
     */
    public function matched(array $keys): array
    {
        $categories = $this->ledgerTypes->categories();
        $entityQuery = $this->ledgerTree->query($keys)->where('traits', '{"tags": ["bank"]}', Comparison::Contains);
        $named = $this->ledgerTree->query($keys)->where('name', 'CASH', Comparison::EqualsIgnoringCase);
        $alike = $this->ledgerTree->query($keys)->where('name', 'cash', Comparison::Similar);
        $parents = $this->ledgerTree->query($keys)->withValuesIn('id', $categories, 'parent_id', [new Condition('name', 'Petty cash')]);

        return [
            'tagged bank: ' . $this->ledgerTree->values($entityQuery->orderBy('name')->rows(), 'name'),
            'named CASH, any case: ' . $this->ledgerTree->values($named->orderBy('name')->rows(), 'name'),
            'alike "cash": ' . $this->ledgerTree->values($alike->orderBy('name')->rows(), 'name'),
            'parent of Petty cash: ' . $this->ledgerTree->values($parents->orderBy('name')->rows(), 'name'),
        ];
    }
}
