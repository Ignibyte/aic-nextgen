<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain;

/**
 * A code lookup's answer (#106): the lines it prints, the indexed files it
 * showed and the scopes it outlined, which the run's `code.queried` event
 * carries.
 */
final readonly class CodeAnswer
{
    /**
     * @param list<string> $lines  What it prints, one item per line.
     * @param list<string> $files  The indexed files it showed.
     * @param list<string> $scopes `*` for the overview, `module:<m>`, `system:<S>` or `path:<p>` for an outline.
     * @param bool         $found  Whether it found what it was asked for.
     */
    public function __construct(
        public array $lines,
        public array $files,
        public array $scopes,
        public bool $found = true,
    ) {
    }
}
