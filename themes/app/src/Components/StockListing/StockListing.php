<?php

declare(strict_types=1);

namespace App\Theme\Components\StockListing;

use AIC\Component\Contracts\Component;

/**
 * The last stock items, from stock-listing.phtml: the latest item's line,
 * how many it shows, then one line each; it polls its own URL.
 */
final readonly class StockListing implements Component
{
    /**
     * @param int          $limit  How many it shows at most.
     * @param list<string> $lines  Each item's line, oldest first.
     * @param string       $latest The latest item's line, or that there is none.
     * @param string       $poll   The URL it polls, a path on this site (#193).
     */
    public function __construct(
        public int $limit,
        public array $lines,
        public string $latest,
        public string $poll,
    ) {
    }
}
