<?php

declare(strict_types=1);

namespace App\Theme\Components\StockLinks;

use AIC\Component\Contracts\Component;
use AIC\Component\Domain\Link;

/**
 * The stock list's links, from stock-links.tpl.php: each refreshes the list
 * alone, through Tessera.
 */
final readonly class StockLinks implements Component
{
    /**
     * @param Link $three   Shows the last 3.
     * @param Link $ten     Shows the last 10.
     * @param Link $refresh Refreshes the list as it is.
     */
    public function __construct(
        public Link $three,
        public Link $ten,
        public Link $refresh,
    ) {
    }
}
