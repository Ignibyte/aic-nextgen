<?php

declare(strict_types=1);

namespace AIC\Component\Domain;

use AIC\Component\Contracts\Component;

/**
 * A hidden form field: a value the form carries back, such as a sealed
 * envelope (#43) or the page to go to next.
 */
final readonly class HiddenInput implements Component
{
    /**
     * @param string $name  The field's name.
     * @param string $value Its value, written even when empty.
     */
    public function __construct(
        public string $name,
        public string $value,
    ) {
    }
}
