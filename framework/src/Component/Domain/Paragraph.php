<?php

declare(strict_types=1);

namespace AIC\Component\Domain;

use AIC\Component\Contracts\Component;

/**
 * A paragraph of plain text, escaped.
 */
final readonly class Paragraph implements Component
{
    /**
     * @param string $text The text.
     * @param string $id   The element id; empty for none.
     */
    public function __construct(
        public string $text,
        public string $id,
    ) {
    }
}
