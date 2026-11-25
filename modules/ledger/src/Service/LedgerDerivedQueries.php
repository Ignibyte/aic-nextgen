<?php

declare(strict_types=1);

namespace App\Ledger\Service;

use AIC\Database\Contracts\QueryException;
use AIC\Database\Contracts\Row;
use AIC\Entity\Application\DerivedQuery;
use AIC\Entity\Domain\Comparison;
use AIC\Entity\Domain\JsonValue;
use AIC\Entity\Domain\Output;
use InvalidArgumentException;

/**
 * Queries over queries (#329) on the ledger's category tree, by name, never
 * by key: conditions, a search and order on computed columns, unions of
 * aligned columns, and a named subquery another query reads.
 */
final readonly class LedgerDerivedQueries
{
    /**
     * @param LedgerTree $ledgerTree Queries the tree's rows.
     */
    public function __construct(
        private LedgerTree $ledgerTree,
    ) {
    }

    /**
     * @param list<int> $keys The tree's keys.
     *
     * @throws InvalidArgumentException When a column or a condition is declared wrongly.
     * @throws QueryException           When a query fails.
     *
     * @return list<string> What conditions and order on a rank, a likeness and a JSON value keep.
     */
    public function computed(array $keys): array
    {
        $ranked = $this->ranked($keys)->where('sibling_rank', 1)->orderBy('name');
        $derivedQuery = $this->ranked($keys)->where('likeness', '0.4', Comparison::GreaterOrEqual)->orderByDescending('likeness');
        $floats = $this->ranked($keys)->search(['kind'], 'FLO');
        $rootless = $this->ranked($keys)->where('parent_id', null)->rows()->first();
        $root = $rootless instanceof Row
            ? $rootless->string('name')
            : 'none';
        $entityQuery = $this->ledgerTree->query($keys)->withValuesOf('id', $this->ranked($keys)->where('sibling_rank', 2), 'id');

        return [
            'first among siblings: ' . $this->ledgerTree->values($ranked->rows(), 'name') . ' of ' . $ranked->count(),
            'likeness 0.4 or more: ' . $this->ledgerTree->values($derivedQuery->rows(), 'name'),
            'kind like FLO: ' . $this->ledgerTree->values($floats->rows(), 'name'),
            'with no parent: ' . $root,
            'second among siblings, by a subquery: ' . $this->ledgerTree->values($entityQuery->rows(), 'name'),
        ];
    }

    /**
     * @param list<int> $keys The tree's keys.
     *
     * @throws InvalidArgumentException When the columns do not align.
     * @throws QueryException           When a query fails.
     *
     * @return list<string> A page of the names and kinds in one list, their count, and the distinct kinds.
     */
    public function joined(array $keys): array
    {
        $names = $this->labels($keys, 'name');
        $derivedQuery = $names->unionAll($this->labels($keys, 'kind'));
        $page = $derivedQuery->orderBy('label')->orderByDescending('source')->limit(3)->offset(2);
        $kinds = $this->labels($keys, 'kind')->unionDistinct($this->labels($keys, 'kind'))->orderBy('label');
        $rows = '';

        foreach ($page->rows() as $row) {
            $rows .= ($rows === '' ? '' : ', ') . $row->string('source') . ' ' . $row->string('label');
        }

        return [
            'names and kinds, rows 3 to 5 of ' . $page->count() . ': ' . $rows,
            'distinct kinds: ' . $this->ledgerTree->values($kinds->rows(), 'label'),
        ];
    }

    /**
     * @param list<int> $keys   The tree's keys.
     * @param string    $column `name` or `kind`.
     *
     * @throws InvalidArgumentException When the query reads no such column.
     *
     * @return DerivedQuery Each category's value as `label`, and the column's name as `source`.
     */
    private function labels(array $keys, string $column): DerivedQuery
    {
        $entityQuery = $this->ledgerTree->query($keys)->withColumns([new JsonValue('kind', 'traits', ['kind'])]);

        return $entityQuery->derived([new Output('label', $column), new Output('source', '', $column)]);
    }

    /**
     * @param list<int> $keys The tree's keys.
     *
     * @throws InvalidArgumentException When a column is declared wrongly.
     *
     * @return DerivedQuery The tree's rows with their rank among siblings, kind and likeness to "cash".
     */
    private function ranked(array $keys): DerivedQuery
    {
        return $this->ledgerTree->ranked($keys)->derived();
    }
}
