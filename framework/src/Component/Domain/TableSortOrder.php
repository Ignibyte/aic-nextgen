<?php

declare(strict_types=1);

namespace AIC\Component\Domain;

/**
 * How a sortable table column is ordered now (#324), written as its header's
 * `aria-sort`: not by this column, ascending or descending.
 */
enum TableSortOrder: string
{
    case None = 'none';
    case Ascending = 'ascending';
    case Descending = 'descending';
}
