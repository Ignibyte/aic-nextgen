<?php

declare(strict_types=1);

namespace App\Theme\Components\{class};

use AIC\Component\Contracts\Component;

/**
 * {class}, made by bin/aic {made}: its title, shown by its template.
 */
final readonly class {class} implements Component
{
    /**
     * @param string $title What it shows.
     */
    public function __construct(
        public string $title,
    ) {
    }
}
