<?php

declare(strict_types=1);

namespace App\Theme\Layouts;

use AIC\Component\Contracts\Component;
use AIC\Component\Contracts\Region;

/** The focus layout's page data (#154): the content and the navigation only, with no header or footer. */
final readonly class FocusPage implements Component
{
    /**
     * @param Region $content The page's primary content and its existing target identity.
     * @param Region $nav     The page's navigation and its existing target identity.
     */
    public function __construct(
        public Region $content,
        public Region $nav,
    ) {
    }
}
