<?php

declare(strict_types=1);

namespace App\Site\Component;

use AIC\Component\Contracts\Component;
use AIC\Component\Contracts\Region;

/** The selected app theme's authored page data, with real typed region properties. */
final readonly class Page implements Component
{
    /**
     * @param Region $header  The optional page header.
     * @param Region $content The page's primary content and its existing target identity.
     * @param Region $nav     The page's navigation and its existing target identity.
     * @param Region $footer  The optional page footer.
     */
    public function __construct(
        public Region $header,
        public Region $content,
        public Region $nav,
        public Region $footer,
    ) {
    }
}
