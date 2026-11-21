<?php

declare(strict_types=1);

namespace App\Scaffold\Service;

use AIC\Component\Application\ListParts;
use AIC\Component\Contracts\Component;
use AIC\Component\Domain\FilterBar;
use AIC\Component\Domain\FilterField;
use AIC\Component\Domain\Heading;
use AIC\Component\Domain\HiddenInput;
use AIC\Component\Domain\Table;
use AIC\Component\Domain\TableAlign;
use AIC\Component\Domain\TableCell;
use AIC\Component\Domain\TableRow;
use AIC\Database\Contracts\QueryException;
use App\Scaffold\Entity\ScaffoldBookListRow;
use App\Scaffold\Form\ScaffoldBookListQueries;
use App\Scaffold\Form\ScaffoldBookListQuery;
use App\Scaffold\Repository\ScaffoldBookListRepository;
use InvalidArgumentException;

use function array_map;
use function min;

/**
 * The components of GET /scaffold/books: its heading, the filter bar, the table of
 * one page of rows with its sortable headers, and the pager; made by bin/aic
 * make:list-page (#324).
 */
final readonly class ScaffoldBookListView
{
    /**
     * The list's path.
     */
    private const string PATH = '/scaffold/books';

    /**
     * How many rows a page shows.
     */
    private const int PER_PAGE = 25;

    /**
     * Every column, in the table's order.
     */
    private const array COLUMNS = [
        'title',
        'pages',
        'price',
        'retired',
    ];

    /**
     * The table's head, in the columns' order.
     */
    private const array HEAD = [
        'Title',
        'Pages',
        'Price',
        'Retired',
    ];

    /**
     * Where each column's text sits, in the columns' order.
     */
    private const array ALIGNMENTS = [
        TableAlign::Start,
        TableAlign::End,
        TableAlign::End,
        TableAlign::Start,
    ];

    /**
     * @param ScaffoldBookListRepository $scaffoldBookListRepository The rows.
     * @param ListParts                  $listParts                  Makes the links and the pager.
     */
    public function __construct(
        private ScaffoldBookListRepository $scaffoldBookListRepository,
        private ListParts $listParts,
    ) {
    }

    /**
     * @param ScaffoldBookListQuery $scaffoldBookListQuery What the list is asked for.
     *
     * @throws QueryException           When the rows cannot be read.
     * @throws InvalidArgumentException When the sort or a filter names no field.
     *
     * @return list<Component> The page's components, in order.
     */
    public function components(ScaffoldBookListQuery $scaffoldBookListQuery): array
    {
        $count = $this->scaffoldBookListRepository->count($scaffoldBookListQuery);
        $pages = $this->listParts->pages($count, self::PER_PAGE);
        $page = min($scaffoldBookListQuery->listPlace->page, $pages);
        $sort = $scaffoldBookListQuery->listPlace->listSort;
        $filters = $scaffoldBookListQuery->scaffoldBookFilters();

        return [
            new Heading('Scaffold books'),
            new FilterBar(self::PATH, $this->fields($scaffoldBookListQuery), [new HiddenInput('sort', $sort->value())]),
            $this->table(
                $scaffoldBookListQuery,
                $this->scaffoldBookListRepository->page($scaffoldBookListQuery, $page, self::PER_PAGE),
            ),
            $this->listParts->pager(self::PATH, $filters, $sort, $page, $pages),
        ];
    }

    /**
     * @param ScaffoldBookListQuery     $scaffoldBookListQuery What the list is asked for.
     * @param list<ScaffoldBookListRow> $rows                  The page's rows, in order.
     *
     * @return Table The rows, with a header link on each sortable column.
     */
    private function table(ScaffoldBookListQuery $scaffoldBookListQuery, array $rows): Table
    {
        $sorts = $this->listParts->sorts(
            self::PATH,
            $scaffoldBookListQuery->scaffoldBookFilters(),
            $scaffoldBookListQuery->listPlace->listSort,
            self::COLUMNS,
            ScaffoldBookListQueries::SORTS,
        );

        return new Table(self::HEAD, array_map(self::tableRow(...), $rows), alignments: self::ALIGNMENTS, sorts: $sorts);
    }

    /**
     * @param ScaffoldBookListQuery $scaffoldBookListQuery What the list is asked for.
     *
     * @return list<FilterField> The filter bar's fields.
     */
    private function fields(ScaffoldBookListQuery $scaffoldBookListQuery): array
    {
        return [
            new FilterField('title', 'Title', $scaffoldBookListQuery->title),
            $this->listParts->choice('retired', 'Retired', $scaffoldBookListQuery->retired),
        ];
    }

    /**
     * @param ScaffoldBookListRow $scaffoldBookListRow A row.
     *
     * @return TableRow Its cells, in the columns' order.
     */
    private static function tableRow(ScaffoldBookListRow $scaffoldBookListRow): TableRow
    {
        return new TableRow([
            new TableCell($scaffoldBookListRow->title),
            new TableCell((string) $scaffoldBookListRow->pages),
            new TableCell($scaffoldBookListRow->price),
            new TableCell($scaffoldBookListRow->retired ? 'yes' : 'no'),
        ]);
    }
}
