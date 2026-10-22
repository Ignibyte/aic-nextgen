<?php

declare(strict_types=1);

namespace AIC\Component\Domain;

use AIC\Component\Contracts\Component;

/**
 * A multi-line text field.
 */
final readonly class Textarea implements Component
{
    /**
     * @param string $id    The element id, which its label names.
     * @param string $name  The field name.
     * @param string $value The text shown.
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $value,
    ) {
    }
}
