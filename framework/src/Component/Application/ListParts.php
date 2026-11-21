<?php

declare(strict_types=1);

namespace AIC\Component\Application;

use AIC\Component\Domain\FilterField;
use AIC\Component\Domain\FilterOption;
use AIC\Component\Domain\ListChoice;
use AIC\Component\Domain\ListSort;
use AIC\Component\Domain\Pager;
use AIC\Component\Domain\TableSort;
use AIC\Component\Domain\TableSortOrder;

use function implode;
use function in_array;
use function intdiv;
use function max;
use function rawurlencode;

/**
 * The parts of a list page that follow from its state (#324): how many pages
 * its rows fill, each sortable column's header link, a choice filter's
 * select, the pager, and the links themselves, which keep the filters and
 * the sort and leave out what is empty.
 */
final readonly class ListParts
{
    /**
     * @param int $count   How many rows match.
     * @param int $perPage How many a page shows, at least 1.
     *
     * @return int How many pages they fill, at least 1.
     */
    public function pages(int $count, int $perPage): int
    {
        return max(1, intdiv($count + $perPage - 1, max(1, $perPage)));
    }

    /**
     * @param string                $path     The list's path.
     * @param array<string, string> $filters  The filters it holds, by query-string name.
     * @param ListSort              $listSort How it is sorted now.
     * @param list<string>          $columns  Every column, in the table's order.
     * @param list<string>          $sortable The columns it may sort by.
     *
     * @return array<int, TableSort> The sortable columns' header links, by position.
     */
    public function sorts(string $path, array $filters, ListSort $listSort, array $columns, array $sortable): array
    {
        $sorts = [];

        foreach ($columns as $position => $column) {
            if (!in_array($column, $sortable, true)) {
                continue;
            }

            $sorts[$position] = $this->sorted($path, $filters, $listSort, $column);
        }

        return $sorts;
    }

    /**
     * @param string     $name       The query-string name.
     * @param string     $label      Its label.
     * @param ListChoice $listChoice The filter's state.
     *
     * @return FilterField A select of any, then each value.
     */
    public function choice(string $name, string $label, ListChoice $listChoice): FilterField
    {
        $options = [new FilterOption('', 'Any')];

        foreach ($listChoice->values as $value) {
            $options[] = new FilterOption($value, $value);
        }

        return new FilterField($name, $label, $listChoice->value, $options);
    }

    /**
     * @param string                $path     The list's path.
     * @param array<string, string> $filters  The filters it holds, by query-string name.
     * @param ListSort              $listSort How it is sorted now.
     * @param int                   $page     The page shown, from 1.
     * @param int                   $pages    How many there are.
     *
     * @return Pager Where the list stands, and the links to the pages either side, which keep the filters and the sort.
     */
    public function pager(string $path, array $filters, ListSort $listSort, int $page, int $pages): Pager
    {
        $values = self::with($filters, 'sort', $listSort->value());

        return new Pager(
            'Page ' . $page . ' of ' . $pages,
            $page > 1 ? $this->href($path, self::with($values, 'page', (string) ($page - 1))) : '',
            $page < $pages ? $this->href($path, self::with($values, 'page', (string) ($page + 1))) : '',
        );
    }

    /**
     * @param string                $path   The list's path.
     * @param array<string, string> $values The query values, by name.
     *
     * @return string The path with the values that are not empty as its query string.
     */
    public function href(string $path, array $values): string
    {
        $pairs = [];

        foreach ($values as $name => $value) {
            if ($value === '') {
                continue;
            }

            $pairs[] = rawurlencode($name) . '=' . rawurlencode($value);
        }

        return $pairs === []
            ? $path
            : $path . '?' . implode('&', $pairs);
    }

    /**
     * @param string                $path     The list's path.
     * @param array<string, string> $filters  The filters it holds.
     * @param ListSort              $listSort How it is sorted now.
     * @param string                $column   A sortable column.
     *
     * @return TableSort Its header link: ascending, or descending when the list is sorted by it ascending already.
     */
    private function sorted(string $path, array $filters, ListSort $listSort, string $column): TableSort
    {
        if ($column !== $listSort->column) {
            return new TableSort($this->href($path, self::with($filters, 'sort', $column)));
        }

        return $listSort->descending
            ? new TableSort($this->href($path, self::with($filters, 'sort', $column)), TableSortOrder::Descending)
            : new TableSort($this->href($path, self::with($filters, 'sort', '-' . $column)), TableSortOrder::Ascending);
    }

    /**
     * @param array<string, string> $values The query values.
     * @param string                $name   One more name.
     * @param string                $value  Its value.
     *
     * @return array<string, string> The values with that one set.
     */
    private static function with(array $values, string $name, string $value): array
    {
        $values[$name] = $value;

        return $values;
    }
}
