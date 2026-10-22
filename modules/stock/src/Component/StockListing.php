<?php

declare(strict_types=1);

namespace App\Stock\Component;

use AIC\Component\Contracts\Component;

/**
 * The last stock items, from StockListing.html.php: how many it shows, then
 * one line each.
 */
final readonly class StockListing implements Component
{
    /**
     * @param int          $limit How many it shows at most.
     * @param list<string> $lines Each item's line, oldest first.
     */
    public function __construct(
        public int $limit,
        public array $lines,
    ) {
    }
}
