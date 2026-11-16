<?php

declare(strict_types=1);

namespace App\Theme\Layouts;

use AIC\Component\Contracts\Component;
use AIC\Component\Contracts\Region;

/** The theme's page data, made by bin/aic make:theme (#148, #153): one Region per region. */
final readonly class Page implements Component
{
    /**
     * @param Region $header  The page header.
     * @param Region $content The primary content and its stable target.
     * @param Region $nav     The navigation and its stable target.
     * @param Region $footer  The page footer.
     */
    public function __construct(
        public Region $header,
        public Region $content,
        public Region $nav,
        public Region $footer,
    ) {
    }
}
