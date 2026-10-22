<?php

declare(strict_types=1);

namespace AIC\Component\Domain;

use AIC\Component\Contracts\Component;

/**
 * A button, submitting its form unless `type` says otherwise.
 */
final readonly class Button implements Component
{
    /**
     * @param string $label The label.
     * @param string $type  `submit`, `button` or `reset`.
     * @param string $id    The element id; empty for none.
     */
    public function __construct(
        public string $label,
        public string $type,
        public string $id,
    ) {
    }
}
