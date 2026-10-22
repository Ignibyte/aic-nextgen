<?php

declare(strict_types=1);

namespace AIC\Component\Domain;

use AIC\Component\Contracts\Component;

/**
 * A password field (#9): it never shows a value back.
 */
final readonly class PasswordInput implements Component
{
    /**
     * @param string $id   The element id, which its label names.
     * @param string $name The field name.
     */
    public function __construct(
        public string $id,
        public string $name,
    ) {
    }
}
