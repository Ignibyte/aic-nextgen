<?php

declare(strict_types=1);

namespace AIC\Component\Domain;

/**
 * Where a list stands (#324): how it is sorted and the page asked for.
 */
final readonly class ListPlace
{
    /**
     * @param ListSort $listSort How it is sorted.
     * @param int      $page     The page asked for, from 1.
     */
    public function __construct(
        public ListSort $listSort,
        public int $page = 1,
    ) {
    }
}
