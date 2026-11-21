<?php

declare(strict_types=1);

namespace App\Scaffold\Repository;

use AIC\Component\Application\ListRows;
use AIC\Database\Contracts\QueryException;
use AIC\Database\Contracts\Row;
use AIC\Entity\Application\EntityQuery;
use AIC\Entity\Application\EntityStore;
use AIC\Entity\Domain\EntityType;
use AIC\Entity\Domain\Field;
use AIC\Entity\Domain\FieldType;
use App\Scaffold\Entity\ScaffoldBookListRow;
use App\Scaffold\Form\ScaffoldBookListQuery;
use InvalidArgumentException;

use function array_map;

/**
 * The `scaffold_books` rows GET /scaffold/books lists, filtered, sorted and a page at a
 * time; every sort ends with `id`, so no row repeats or goes missing
 * between pages. Made by bin/aic make:list-page (#324).
 */
final readonly class ScaffoldBookListRepository
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
     * @param ScaffoldBookListQuery $scaffoldBookListQuery What the list is asked for.
     *
     * @throws QueryException           When the rows cannot be counted.
     * @throws InvalidArgumentException When a filter names no field.
     *
     * @return int How many rows match its filters.
     */
    public function count(ScaffoldBookListQuery $scaffoldBookListQuery): int
    {
        return $this->matching($scaffoldBookListQuery)->totals()->count();
    }

    /**
     * @param ScaffoldBookListQuery $scaffoldBookListQuery What the list is asked for.
     * @param int                   $page                  The page, from 1.
     * @param int                   $perPage               How many rows a page shows.
     *
     * @throws QueryException           When the rows cannot be read.
     * @throws InvalidArgumentException When the sort or a filter names no field.
     *
     * @return list<ScaffoldBookListRow> That page's rows, in order.
     */
    public function page(ScaffoldBookListQuery $scaffoldBookListQuery, int $page, int $perPage): array
    {
        $rows = $this->listRows->page(
            $this->matching($scaffoldBookListQuery),
            $scaffoldBookListQuery->listPlace->listSort,
            'id',
            $page,
            $perPage,
        );

        return array_map(self::scaffoldBookListRow(...), $rows);
    }

    /**
     * @param ScaffoldBookListQuery $scaffoldBookListQuery What the list is asked for.
     *
     * @throws InvalidArgumentException When a filter names no field.
     *
     * @return EntityQuery The rows its filters match.
     */
    private function matching(ScaffoldBookListQuery $scaffoldBookListQuery): EntityQuery
    {
        return $this->entityStore->query($this->scaffoldBooksType())
            ->search(['title'], $scaffoldBookListQuery->title)
            ->whereAny($this->listRows->flags('retired', $scaffoldBookListQuery->retired));
    }

    /**
     * @throws InvalidArgumentException When the type is invalid.
     *
     * @return EntityType The `scaffold_books` table's listed fields.
     */
    private function scaffoldBooksType(): EntityType
    {
        return new EntityType('scaffold_books', 'id', [
            new Field('title', FieldType::Text),
            new Field('pages', FieldType::Integer),
            new Field('price', FieldType::Numeric),
            new Field('retired', FieldType::Boolean),
        ]);
    }

    /**
     * @param Row $row A row.
     *
     * @throws QueryException When a column is missing or of another type.
     *
     * @return ScaffoldBookListRow Its listed values.
     */
    private static function scaffoldBookListRow(Row $row): ScaffoldBookListRow
    {
        return new ScaffoldBookListRow(
            $row->string('title'),
            $row->int('pages'),
            $row->string('price'),
            $row->bool('retired'),
        );
    }
}
