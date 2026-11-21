<?php

declare(strict_types=1);

namespace AIC\Component\Domain;

use AIC\Component\Contracts\Component;

/**
 * The pages of a list (#324): where the list stands, such as "Page 2 of 5",
 * and the links to the page before and the page after, each left out when
 * there is none. It renders as a `nav` with the class `aic-pager`.
 */
final readonly class Pager implements Component
{
    /**
     * @param string $label    Where the list stands, shown between the links.
     * @param string $previous The same-origin URL of the page before; empty on the first.
     * @param string $next     The same-origin URL of the page after; empty on the last.
     */
    public function __construct(
        public string $label,
        public string $previous = '',
        public string $next = '',
    ) {
    }
}
