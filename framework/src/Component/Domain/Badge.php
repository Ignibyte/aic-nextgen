<?php

declare(strict_types=1);

namespace AIC\Component\Domain;

use AIC\Component\Contracts\Component;

/**
 * A short tag or count beside something.
 */
final readonly class Badge implements Component
{
    /**
     * @param string $text The tag or the count.
     * @param string $id   The element id; empty for none.
     */
    public function __construct(
        public string $text,
        public string $id,
    ) {
    }
}
