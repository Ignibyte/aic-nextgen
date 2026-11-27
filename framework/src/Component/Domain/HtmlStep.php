<?php

declare(strict_types=1);

namespace AIC\Component\Domain;

/**
 * One step of the tokenizer (#337): what it read, if anything it keeps, and
 * where the next read starts.
 */
final readonly class HtmlStep
{
    /**
     * @param HtmlText|HtmlStartTag|HtmlEndTag|null $token What it read; null for a comment, a declaration or a tag left open.
     * @param int                                   $next  Where the next read starts.
     */
    public function __construct(
        public HtmlText|HtmlStartTag|HtmlEndTag|null $token,
        public int $next,
    ) {
    }
}
