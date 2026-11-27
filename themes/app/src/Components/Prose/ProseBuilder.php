<?php

declare(strict_types=1);

namespace App\Theme\Components\Prose;

use AIC\Component\Domain\RawHtml;
use AIC\Component\Domain\SanitizedHtml;

/**
 * Builds a Prose from what `HtmlSanitizer` made (#337): the theme's one
 * RawHtml grant, whose trust is the sanitizer's, since only the sanitizer
 * makes a SanitizedHtml (gate:8 `aic.sanitizedHtml`).
 */
final readonly class ProseBuilder
{
    /**
     * @param SanitizedHtml $sanitizedHtml What the sanitizer made.
     *
     * @return Prose It shown.
     */
    public function prose(SanitizedHtml $sanitizedHtml): Prose
    {
        return new Prose(new RawHtml($sanitizedHtml->markup));
    }
}
