<?php

declare(strict_types=1);

namespace App\Ledger\Service;

use AIC\Database\Contracts\QueryException;
use AIC\Database\Contracts\Rows;
use AIC\Entity\Application\EntityQuery;
use AIC\Entity\Application\EntityStore;
use AIC\Entity\Domain\Comparison;
use AIC\Entity\Domain\Condition;
use AIC\Entity\Domain\Direction;
use AIC\Entity\Domain\JsonValue;
use AIC\Entity\Domain\Similarity;
use AIC\Entity\Domain\Window;
use AIC\Entity\Domain\WindowFunction;
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
     * @param EntityStore $entityStore The entity store.
     * @param LedgerTypes $ledgerTypes The ledger's entity types.
     */
    public function __construct(
        private EntityStore $entityStore,
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
        $rows = $this->tree($keys)->withColumns([
            new Window('sibling_rank', WindowFunction::RowNumber, ['parent_id'], 'name', Direction::Ascending),
            new JsonValue('kind', 'traits', ['kind']),
            new Similarity('likeness', 'name', 'cash'),
        ])->orderBy('name')->rows();
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
        $entityQuery = $this->tree($keys)->where('traits', '{"tags": ["bank"]}', Comparison::Contains);
        $named = $this->tree($keys)->where('name', 'CASH', Comparison::EqualsIgnoringCase);
        $alike = $this->tree($keys)->where('name', 'cash', Comparison::Similar);
        $parents = $this->tree($keys)->withValuesIn('id', $categories, 'parent_id', [new Condition('name', 'Petty cash')]);

        return [
            'tagged bank: ' . self::names($entityQuery->orderBy('name')->rows()),
            'named CASH, any case: ' . self::names($named->orderBy('name')->rows()),
            'alike "cash": ' . self::names($alike->orderBy('name')->rows()),
            'parent of Petty cash: ' . self::names($parents->orderBy('name')->rows()),
        ];
    }

    /**
     * @param list<int> $keys The tree's keys.
     *
     * @throws InvalidArgumentException When a condition is declared wrongly.
     *
     * @return EntityQuery A query over the tree's rows alone.
     */
    private function tree(array $keys): EntityQuery
    {
        $conditions = [];

        foreach ($keys as $key) {
            $conditions[] = new Condition('id', $key);
        }

        return $this->entityStore->query($this->ledgerTypes->categories())->whereAny($conditions);
    }

    /**
     * @param Rows $rows Categories.
     *
     * @throws QueryException When a row lacks its name.
     *
     * @return string Their names, joined.
     */
    private static function names(Rows $rows): string
    {
        $names = '';

        foreach ($rows as $row) {
            $names .= ($names === '' ? '' : ', ') . $row->string('name');
        }

        return $names === ''
            ? 'none'
            : $names;
    }
}
