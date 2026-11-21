<?php

declare(strict_types=1);

namespace AIC\Component\Domain;

/**
 * A sortable table column's header (#324): the link that sorts by it, and how
 * the rows are ordered by it now.
 */
final readonly class TableSort
{
    /**
     * @param string         $href           The same-origin URL of the rows sorted by this column.
     * @param TableSortOrder $tableSortOrder How the rows shown are ordered by it.
     */
    public function __construct(
        public string $href,
        public TableSortOrder $tableSortOrder = TableSortOrder::None,
    ) {
    }
}
