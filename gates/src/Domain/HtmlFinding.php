<?php

declare(strict_types=1);

namespace Gates\Domain;

use function sprintf;

/**
 * One thing the HTML lint refuses (#170): its line, its rule and the fix.
 */
final readonly class HtmlFinding
{
    /**
     * @param int      $line     The line it is on.
     * @param HtmlRule $htmlRule What it breaks.
     * @param string   $detail   What is refused, and the fix.
     */
    public function __construct(
        public int $line,
        public HtmlRule $htmlRule,
        public string $detail,
    ) {
    }

    /**
     * @return string `rule: detail`, the part a gate prints after the place.
     */
    public function message(): string
    {
        return sprintf('%s: %s', $this->htmlRule->value, $this->detail);
    }
}
