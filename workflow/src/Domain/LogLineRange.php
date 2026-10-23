<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain;

/** An explicitly requested transcript window, with one-based lines and an optional count. */
final readonly class LogLineRange
{
    /**
     * @param int      $first The first requested line, starting at one.
     * @param int|null $count The maximum lines to return; null means the remaining lines.
     */
    public function __construct(public int $first, public ?int $count)
    {
    }
}
