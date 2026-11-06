<?php

declare(strict_types=1);

namespace App\Theme\Layouts;

use AIC\Component\Contracts\Component;
use AIC\Component\Contracts\Region;

/**
 * The page layout's regions: each constructor property is a region a page or
 * a block fills. Its markup is layouts/page.phtml.
 */
final readonly class Page implements Component
{
    /**
     * @param Region $content The page's own components.
     * @param Region $footer  Blocks under the content.
     */
    public function __construct(
        public Region $content,
        public Region $footer,
    ) {
    }
}
