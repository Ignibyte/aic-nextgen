<?php

declare(strict_types=1);

namespace AIC\Component\Domain;

/**
 * How a list is sorted (#324): by one column, ascending or descending, as
 * its query string writes it: `sort=<column>`, or `sort=-<column>`.
 */
final readonly class ListSort
{
    /**
     * @param string $column     The column.
     * @param bool   $descending Whether it runs from the highest down.
     */
    public function __construct(
        public string $column,
        public bool $descending = false,
    ) {
    }

    /**
     * @return string The sort as the query string writes it.
     */
    public function value(): string
    {
        return ($this->descending ? '-' : '') . $this->column;
    }
}
