<?php

declare(strict_types=1);

namespace App\Theme\Components\LineList;

use AIC\Component\Contracts\Component;

/**
 * A list of plain lines, from line-list.phtml, or a line saying there are
 * none: the jobs page's schedules, waiting and failed jobs, and the latest
 * notifications.
 */
final readonly class LineList implements Component
{
    /**
     * @param string       $id    The list's element id.
     * @param list<string> $lines The lines, in order.
     * @param string       $none  What it says when there are none.
     */
    public function __construct(
        public string $id,
        public array $lines,
        public string $none,
    ) {
    }
}
