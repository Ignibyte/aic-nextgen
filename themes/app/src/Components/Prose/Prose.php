<?php

declare(strict_types=1);

namespace App\Theme\Components\Prose;

use AIC\Component\Contracts\Component;
use AIC\Component\Domain\RawHtml;

/**
 * Markup a visitor wrote, once `HtmlSanitizer` has made it safe (#337),
 * written as it is. Only `ProseBuilder` makes its RawHtml, from a
 * SanitizedHtml.
 */
final readonly class Prose implements Component
{
    /**
     * @param RawHtml $body The sanitized markup.
     */
    public function __construct(
        public RawHtml $body,
    ) {
    }
}
