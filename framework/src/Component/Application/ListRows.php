<?php

declare(strict_types=1);

namespace AIC\Component\Application;

use AIC\Component\Domain\ListChoice;
use AIC\Component\Domain\ListSort;
use AIC\Database\Contracts\QueryException;
use AIC\Database\Contracts\Row;
use AIC\Entity\Application\EntityQuery;
use AIC\Entity\Domain\Condition;
use InvalidArgumentException;

/**
 * One page of a list's rows (#324): the query sorted as the list asks, then
 * by the table's key so that no row repeats or goes missing between pages,
 * and cut to the page; and the conditions of a boolean filter.
 */
final readonly class ListRows
{
    /**
     * @param EntityQuery $entityQuery The rows the list's filters match.
     * @param ListSort    $listSort    How the list is sorted.
     * @param string      $key         The table's key, which ends the sort.
     * @param int         $page        The page, from 1.
     * @param int         $perPage     How many rows a page shows, at least 1.
     *
     * @throws QueryException           When the rows cannot be read.
     * @throws InvalidArgumentException When the sort or the key names no field, or the page size is below 1.
     *
     * @return list<Row> The page's rows, in order.
     */
    public function page(EntityQuery $entityQuery, ListSort $listSort, string $key, int $page, int $perPage): array
    {
        $sorted = $listSort->descending
            ? $entityQuery->orderByDescending($listSort->column)
            : $entityQuery->orderBy($listSort->column);

        return $sorted->orderBy($key)->limit($perPage)->offset(($page - 1) * $perPage)->rows()->all();
    }

    /**
     * @param string     $field      A boolean field.
     * @param ListChoice $listChoice Its filter, whose values are `yes` and `no`.
     *
     * @return list<Condition> A condition for each value it matches, any one of which may hold.
     */
    public function flags(string $field, ListChoice $listChoice): array
    {
        $conditions = [];

        foreach ($listChoice->matches() as $value) {
            $conditions[] = new Condition($field, $value === 'yes');
        }

        return $conditions;
    }
}
