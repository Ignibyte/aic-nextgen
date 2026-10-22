<?php

declare(strict_types=1);

namespace AIC\Component\Domain;

use AIC\Component\Contracts\Component;

/**
 * A checkbox that posts `1` when it is ticked and nothing when it is not.
 */
final readonly class Checkbox implements Component
{
    /**
     * @param string $id      The element id, which its label names.
     * @param string $name    The field name.
     * @param bool   $checked Whether it is ticked.
     */
    public function __construct(
        public string $id,
        public string $name,
        public bool $checked,
    ) {
    }
}
