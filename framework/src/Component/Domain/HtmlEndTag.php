<?php

declare(strict_types=1);

namespace AIC\Component\Domain;

/**
 * An end tag (#337).
 */
final readonly class HtmlEndTag
{
    /**
     * @param string $element The element, lower case.
     */
    public function __construct(
        public string $element,
    ) {
    }
}
