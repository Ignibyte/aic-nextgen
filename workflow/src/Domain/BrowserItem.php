<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain;

/**
 * One `## Browser Check` item (#174): the look it names, whether it is
 * ticked, and what the bot or the human decided to do with the look's test.
 */
final readonly class BrowserItem
{
    /**
     * @param int    $number   Its place in the list, from 1.
     * @param string $spec     The look's test, `tests/browser/<name>.spec.ts`; empty when it names none.
     * @param bool   $ticked   Whether it is ticked.
     * @param string $decision `keep` or `drop`, written after `saw:`; empty until decided.
     */
    public function __construct(
        public int $number,
        public string $spec,
        public bool $ticked,
        public string $decision,
    ) {
    }
}
