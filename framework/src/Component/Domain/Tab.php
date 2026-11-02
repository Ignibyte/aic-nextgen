<?php

declare(strict_types=1);

namespace AIC\Component\Domain;

/**
 * One tab of `Tabs` (#230).
 */
final readonly class Tab
{
    /**
     * @param string $label   What it shows.
     * @param string $href    Where it leads.
     * @param bool   $current Whether it is the one shown.
     */
    public function __construct(
        public string $label,
        public string $href,
        public bool $current = false,
    ) {
    }
}
