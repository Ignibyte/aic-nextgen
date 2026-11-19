<?php

declare(strict_types=1);

namespace App\Theme\Components\FeedEntry;

use AIC\Component\Contracts\Component;

/**
 * One post in the ledger feed (#314), appended to the Feed list as an element patch.
 */
final readonly class FeedEntry implements Component
{
    /**
     * @param string $text What was posted.
     */
    public function __construct(
        public string $text,
    ) {
    }
}
