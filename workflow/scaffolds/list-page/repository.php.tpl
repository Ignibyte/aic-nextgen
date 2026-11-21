<?php

declare(strict_types=1);

namespace {namespace}\Repository;

use AIC\Component\Application\ListRows;
use AIC\Database\Contracts\QueryException;
use AIC\Database\Contracts\Row;
use AIC\Entity\Application\EntityQuery;
use AIC\Entity\Application\EntityStore;
use AIC\Entity\Domain\EntityType;
use AIC\Entity\Domain\Field;
use AIC\Entity\Domain\FieldType;
use {namespace}\Entity\{stem}ListRow;
use {namespace}\Form\{stem}ListQuery;
use InvalidArgumentException;

use function array_map;

/**
 * The `{table}` rows GET {path} lists, filtered, sorted and a page at a
 * time; every sort ends with `{key}`, so no row repeats or goes missing
 * between pages. Made by bin/aic make:list-page (#324).
 */
final readonly class {stem}ListRepository
{
    /**
     * @param EntityStore $entityStore The rows.
     * @param ListRows    $listRows    Sorts them and cuts a page.
     */
    public function __construct(
        private EntityStore $entityStore,
        private ListRows $listRows,
    ) {
    }

    /**
     * @param {stem}ListQuery ${variable}ListQuery What the list is asked for.
     *
     * @throws QueryException           When the rows cannot be counted.
     * @throws InvalidArgumentException When a filter names no field.
     *
     * @return int How many rows match its filters.
     */
    public function count({stem}ListQuery ${variable}ListQuery): int
    {
        return $this->matching(${variable}ListQuery)->totals()->count();
    }

    /**
{pageParams}     *
     * @throws QueryException           When the rows cannot be read.
     * @throws InvalidArgumentException When the sort or a filter names no field.
     *
     * @return list<{stem}ListRow> That page's rows, in order.
     */
    public function page({stem}ListQuery ${variable}ListQuery, int $page, int $perPage): array
    {
        $rows = $this->listRows->page(
            $this->matching(${variable}ListQuery),
            ${variable}ListQuery->listPlace->listSort,
            '{key}',
            $page,
            $perPage,
        );

        return array_map(self::{variable}ListRow(...), $rows);
    }

    /**
     * @param {stem}ListQuery ${variable}ListQuery What the list is asked for.
     *
     * @throws InvalidArgumentException When a filter names no field.
     *
     * @return EntityQuery The rows its filters match.
     */
    private function matching({stem}ListQuery ${variable}ListQuery): EntityQuery
    {
        return $this->entityStore->query($this->{type}())
{matching};
    }

    /**
     * @throws InvalidArgumentException When the type is invalid.
     *
     * @return EntityType The `{table}` table's listed fields.
     */
    private function {type}(): EntityType
    {
        return new EntityType('{table}', '{key}', [
{fields}        ]);
    }

    /**
     * @param Row $row A row.
     *
     * @throws QueryException When a column is missing or of another type.
     *
     * @return {stem}ListRow Its listed values.
     */
    private static function {variable}ListRow(Row $row): {stem}ListRow
    {
        return new {stem}ListRow(
{rowReads}        );
    }
}
