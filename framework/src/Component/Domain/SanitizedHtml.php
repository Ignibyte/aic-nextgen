<?php

declare(strict_types=1);

namespace AIC\Component\Domain;

/**
 * Markup `HtmlSanitizer` made from untrusted HTML (#337): only the elements
 * and attributes its policy allows, every text and value escaped again. Only
 * the sanitizer makes one (gate:8 `aic.sanitizedHtml`); a theme component
 * shows it through its RawHtml grant.
 */
final readonly class SanitizedHtml
{
    /**
     * @param string $markup The sanitized markup.
     */
    public function __construct(
        public string $markup,
    ) {
    }
}
