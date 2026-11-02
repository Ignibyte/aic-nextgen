<?php

declare(strict_types=1);

namespace AIC\Component\Domain;

use AIC\Component\Contracts\Component;

/**
 * Preformatted text, escaped (#230): source, markup or SQL shown as written.
 */
final readonly class CodeBlock implements Component
{
    /**
     * @param string $text The text, as it is; the template escapes it.
     */
    public function __construct(
        public string $text,
    ) {
    }
}
