<?php

declare(strict_types=1);

namespace App\Theme\Components\ScaffoldGauge;

use AIC\Component\Contracts\Component;

/**
 * ScaffoldGauge, made by bin/aic make:component --script (#315): its title, and an element Tessera mounts
 * its script on, the theme's `assets/components/scaffold-gauge.js`, with its data.
 */
final readonly class ScaffoldGauge implements Component
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
