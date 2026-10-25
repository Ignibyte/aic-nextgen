<?php

declare(strict_types=1);

namespace AIC\Component\Domain;

use function htmlspecialchars;

use const ENT_HTML5;
use const ENT_QUOTES;
use const ENT_SUBSTITUTE;

/**
 * The one escaping path of the atoms: text and attribute values through
 * `htmlspecialchars` with quotes, invalid sequences substituted, HTML5.
 */
trait RendersMarkup
{
    /**
     * @param string $text Text.
     *
     * @return string It, escaped for an element's content or an attribute value.
     */
    private static function text(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }
}
