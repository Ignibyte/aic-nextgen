<?php

declare(strict_types=1);

namespace AIC\Component\Domain;

use AIC\Component\Contracts\Component;

/**
 * A link to a page of the site.
 */
final readonly class Link implements Component
{
    /**
     * @param string $label   The text.
     * @param string $href    Where it goes, a path on this site.
     * @param bool   $current Whether it is the page being shown (`aria-current="page"`).
     * @param string $target  The Tessera targets it updates (`data-target`); empty for the default.
     */
    public function __construct(
        public string $label,
        public string $href,
        public bool $current = false,
        public string $target = '',
    ) {
    }
}
