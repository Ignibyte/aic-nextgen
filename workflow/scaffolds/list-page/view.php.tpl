<?php

declare(strict_types=1);

namespace {namespace}\Service;

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
use {namespace}\Entity\{stem}ListRow;
use {namespace}\Form\{stem}ListQueries;
use {namespace}\Form\{stem}ListQuery;
use {namespace}\Repository\{stem}ListRepository;
use InvalidArgumentException;

use function array_map;
use function min;

/**
 * The components of GET {path}: its heading, the filter bar, the table of
 * one page of rows with its sortable headers, and the pager; made by bin/aic
 * make:list-page (#324).
 */
final readonly class {stem}ListView
{
    /**
     * The list's path.
     */
    private const string PATH = '{path}';

    /**
     * How many rows a page shows.
     */
    private const int PER_PAGE = {perPage};

    /**
     * Every column, in the table's order.
     */
    private const array COLUMNS = [
{columns}    ];

    /**
     * The table's head, in the columns' order.
     */
    private const array HEAD = [
{head}    ];

    /**
     * Where each column's text sits, in the columns' order.
     */
    private const array ALIGNMENTS = [
{alignments}    ];

    /**
{viewParams}     */
    public function __construct(
        private {stem}ListRepository ${variable}ListRepository,
        private ListParts $listParts,
    ) {
    }

    /**
     * @param {stem}ListQuery ${variable}ListQuery What the list is asked for.
     *
     * @throws QueryException           When the rows cannot be read.
     * @throws InvalidArgumentException When the sort or a filter names no field.
     *
     * @return list<Component> The page's components, in order.
     */
    public function components({stem}ListQuery ${variable}ListQuery): array
    {
        $count = $this->{variable}ListRepository->count(${variable}ListQuery);
        $pages = $this->listParts->pages($count, self::PER_PAGE);
        $page = min(${variable}ListQuery->listPlace->page, $pages);
        $sort = ${variable}ListQuery->listPlace->listSort;
        $filters = ${variable}ListQuery->{variable}Filters();

        return [
            new Heading('{title}'),
            new FilterBar(self::PATH, $this->fields(${variable}ListQuery), [new HiddenInput('sort', $sort->value())]),
            $this->table(
                ${variable}ListQuery,
                $this->{variable}ListRepository->page(${variable}ListQuery, $page, self::PER_PAGE),
            ),
            $this->listParts->pager(self::PATH, $filters, $sort, $page, $pages),
        ];
    }

    /**
{tableParams}     *
     * @return Table The rows, with a header link on each sortable column.
     */
    private function table({stem}ListQuery ${variable}ListQuery, array $rows): Table
    {
        $sorts = $this->listParts->sorts(
            self::PATH,
            ${variable}ListQuery->{variable}Filters(),
            ${variable}ListQuery->listPlace->listSort,
            self::COLUMNS,
            {stem}ListQueries::SORTS,
        );

        return new Table(self::HEAD, array_map(self::tableRow(...), $rows), alignments: self::ALIGNMENTS, sorts: $sorts);
    }

    /**
     * @param {stem}ListQuery ${variable}ListQuery What the list is asked for.
     *
     * @return list<FilterField> The filter bar's fields.
     */
    private function fields({stem}ListQuery ${variable}ListQuery): array
    {
        return [
{fieldLines}        ];
    }

    /**
     * @param {stem}ListRow ${variable}ListRow A row.
     *
     * @return TableRow Its cells, in the columns' order.
     */
    private static function tableRow({stem}ListRow ${variable}ListRow): TableRow
    {
        return new TableRow([
{cells}        ]);
    }
}
