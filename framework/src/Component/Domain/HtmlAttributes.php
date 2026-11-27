<?php

declare(strict_types=1);

namespace AIC\Component\Domain;

/**
 * A start tag's attributes as the tokenizer read them (#337), and where the
 * tag ends.
 */
final readonly class HtmlAttributes
{
    /**
     * @param array<string, string> $values Each attribute's value by its lower-case name, the first of each name.
     * @param int                   $next   Just past the tag's `>`; -1 when the input ends inside the tag.
     */
    public function __construct(
        public array $values,
        public int $next,
    ) {
    }
}
