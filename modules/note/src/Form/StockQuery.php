<?php

declare(strict_types=1);

namespace App\Note\Form;

/**
 * What the stock list was asked for (#184): a search in the names, a price
 * range, some SKUs, whether only the items not yet counted, and a page, each
 * already checked. Empty values ask for nothing, so a request without them
 * reads the newest items as before.
 */
final readonly class StockQuery
{
    /**
     * @param string       $search    Text the name contains; empty for any.
     * @param string       $minPrice  The lowest price, a decimal string; empty for none.
     * @param string       $maxPrice  The highest price, a decimal string; empty for none.
     * @param list<string> $skus      The SKUs to show; empty for any.
     * @param bool         $uncounted Whether to show only the items with no quantity or none left.
     * @param int          $page      The page, from 1.
     */
    public function __construct(
        public string $search = '',
        public string $minPrice = '',
        public string $maxPrice = '',
        public array $skus = [],
        public bool $uncounted = false,
        public int $page = 1,
    ) {
    }

    /**
     * @return bool Whether it asks for anything but the newest items: a search, a price, SKUs or the uncounted.
     */
    public function isFiltered(): bool
    {
        return $this->search !== '' || $this->minPrice !== '' || $this->maxPrice !== '' || $this->skus !== [] || $this->uncounted;
    }
}
