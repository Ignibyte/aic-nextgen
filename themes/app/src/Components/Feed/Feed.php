<?php

declare(strict_types=1);

namespace App\Theme\Components\Feed;

use AIC\Component\Contracts\Component;

/**
 * The ledger feed (#314), from feed.phtml: a list Tessera fills from a
 * broadcast stream, one entry per post.
 */
final readonly class Feed implements Component
{
    /**
     * @param string $stream The stream that fills the list, such as `/ledger/feed/events`.
     */
    public function __construct(
        public string $stream,
    ) {
    }
}
