<?php

declare(strict_types=1);

namespace App\Ledger\Service;

use AIC\Database\Contracts\QueryException;
use AIC\Database\Contracts\Rows;
use AIC\Entity\Application\EntityQuery;
use AIC\Entity\Application\EntityStore;
use AIC\Entity\Domain\Condition;
use AIC\Entity\Domain\Direction;
use AIC\Entity\Domain\JsonValue;
use AIC\Entity\Domain\Similarity;
use AIC\Entity\Domain\Window;
use AIC\Entity\Domain\WindowFunction;
use App\Ledger\Entity\LedgerTypes;
use InvalidArgumentException;

/**
 * The category tree one request wrote, for the query forms of #311 and #329:
 * a query over its rows alone, its computed columns, and its rows' values.
 */
final readonly class LedgerTree
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
     * @throws InvalidArgumentException When a condition is declared wrongly.
     *
     * @return EntityQuery A query over the tree's rows alone.
     */
    public function query(array $keys): EntityQuery
    {
        $conditions = [];

        foreach ($keys as $key) {
            $conditions[] = new Condition('id', $key);
        }

        return $this->entityStore->query($this->ledgerTypes->categories())->whereAny($conditions);
    }

    /**
     * @param list<int> $keys The tree's keys.
     *
     * @throws InvalidArgumentException When a column is declared wrongly.
     *
     * @return EntityQuery The tree's rows with their rank among siblings, kind and likeness to "cash".
     */
    public function ranked(array $keys): EntityQuery
    {
        return $this->query($keys)->withColumns([
            new Window('sibling_rank', WindowFunction::RowNumber, ['parent_id'], 'name', Direction::Ascending),
            new JsonValue('kind', 'traits', ['kind']),
            new Similarity('likeness', 'name', 'cash'),
        ]);
    }

    /**
     * @param Rows   $rows   Rows.
     * @param string $column The column to read.
     *
     * @throws QueryException When a row lacks it.
     *
     * @return string The column's values, joined; `none` for no row.
     */
    public function values(Rows $rows, string $column): string
    {
        $values = '';

        foreach ($rows as $row) {
            $values .= ($values === '' ? '' : ', ') . $row->string($column);
        }

        return $values === ''
            ? 'none'
            : $values;
    }
}
