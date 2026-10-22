<?php

declare(strict_types=1);

namespace AIC\Component\Domain;

use AIC\Component\Contracts\Component;

/**
 * A section heading (`<h2>`), under the document's one `<h1>`.
 */
final readonly class Heading implements Component
{
    /**
     * @param string $text The heading.
     */
    public function __construct(
        public string $text,
    ) {
    }
}
