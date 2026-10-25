<?php

declare(strict_types=1);

namespace App\Scaffold\Component;

use AIC\Component\Contracts\Component;

/**
 * ScaffoldCard, made by bin/aic make:component (#122): its title, shown by its declared template.
 */
final readonly class ScaffoldCard implements Component
{
    /**
     * @param string $title What it shows.
     */
    public function __construct(
        public string $title,
    ) {
    }
}
