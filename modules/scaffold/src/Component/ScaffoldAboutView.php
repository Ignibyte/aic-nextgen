<?php

declare(strict_types=1);

namespace App\Scaffold\Component;

use AIC\Component\Contracts\Component;

/**
 * ScaffoldAboutView, made by bin/aic make:page (#122) for ScaffoldAboutPage: its title, shown by its declared template.
 */
final readonly class ScaffoldAboutView implements Component
{
    /**
     * @param string $title What it shows.
     */
    public function __construct(
        public string $title,
    ) {
    }
}
