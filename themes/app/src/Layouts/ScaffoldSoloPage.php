<?php

declare(strict_types=1);

namespace App\Theme\Layouts;

use AIC\Component\Contracts\Component;
use AIC\Component\Contracts\Region;

/**
 * The scaffold-solo layout's page data, made by bin/aic make:layout (#157): the content region only.
 */
final readonly class ScaffoldSoloPage implements Component
{
    /**
     * @param Region $content The page's primary content and its stable target.
     */
    public function __construct(
        public Region $content,
    ) {
    }
}
