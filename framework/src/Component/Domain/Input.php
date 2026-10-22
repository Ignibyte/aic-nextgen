<?php

declare(strict_types=1);

namespace AIC\Component\Domain;

use AIC\Component\Contracts\Component;

/**
 * A one-line text field.
 */
final readonly class Input implements Component
{
    /**
     * @param string $id      The element id, which its label names.
     * @param string $name    The field name.
     * @param string $value   The value shown.
     * @param bool   $invalid Whether the value was refused (`aria-invalid="true"`, #71).
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $value,
        public bool $invalid = false,
    ) {
    }
}
