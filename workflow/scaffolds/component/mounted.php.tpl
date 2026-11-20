<?php

declare(strict_types=1);

namespace App\Theme\Components\{class};

use AIC\Component\Contracts\Component;

/**
 * {class}, made by bin/aic {made}: its title, and an element Tessera mounts
 * its script on, the theme's `assets/components/{kebab}.js`, with its data.
 */
final readonly class {class} implements Component
{
    /**
     * @param string $title What it shows.
     * @param string $data  The script's data, a JSON object.
     */
    public function __construct(
        public string $title,
        public string $data = '{}',
    ) {
    }
}
