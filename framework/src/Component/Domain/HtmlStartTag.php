<?php

declare(strict_types=1);

namespace AIC\Component\Domain;

/**
 * A start tag (#337): its name and its attributes, each name lower case and
 * kept the first time it appears, each value as the input had it.
 */
final readonly class HtmlStartTag
{
    /**
     * @param string                $tag        The element, lower case.
     * @param array<string, string> $attributes Its attributes, by name.
     */
    public function __construct(
        public string $tag,
        public array $attributes,
    ) {
    }
}
