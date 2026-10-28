<?php

declare(strict_types=1);

namespace App\Note\Service;

use AIC\Component\Domain\Link;
use App\Theme\Components\StockLinks\StockLinks;

/**
 * The stock list's links (#74): each targets the list by its anchor, so
 * Tessera refreshes it alone wherever the page places it (#188, #194).
 */
final readonly class StockListLinks
{
    /**
     * @param string $anchor The list's anchor.
     *
     * @return StockLinks The links that show three or ten, and the one that refreshes the list alone.
     */
    public function links(string $anchor): StockLinks
    {
        return new StockLinks(
            new Link('Show the last 3', '/stock?limit=3', false, '@' . $anchor),
            new Link('Show the last 10', '/stock?limit=10', false, '@' . $anchor),
            new Link('Refresh the list', '/stock', false, '@' . $anchor),
        );
    }
}
