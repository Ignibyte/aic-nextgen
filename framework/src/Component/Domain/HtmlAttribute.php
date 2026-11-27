<?php

declare(strict_types=1);

namespace AIC\Component\Domain;

/**
 * One attribute as the tokenizer read it (#337), and where the next starts.
 */
final readonly class HtmlAttribute
{
    /**
     * @param string $name  Its name, lower case.
     * @param string $value Its value as the input had it; empty when it has none.
     * @param int    $next  Where the next read starts; the input's end when a quote is never closed.
     */
    public function __construct(
        public string $name,
        public string $value,
        public int $next,
    ) {
    }
}
