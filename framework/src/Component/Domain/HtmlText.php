<?php

declare(strict_types=1);

namespace AIC\Component\Domain;

/**
 * Text between tags, as the input had it (#337).
 */
final readonly class HtmlText
{
    /**
     * @param string $text The text, references and all.
     */
    public function __construct(
        public string $text,
    ) {
    }
}
