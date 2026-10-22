<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use DateTimeZone;
use Psr\Clock\ClockInterface;

/**
 * The workflow's timestamps (#100): the clock's now, in UTC, as the tickets
 * write times (`Y-m-d\TH:i:s\Z`).
 */
final readonly class UtcTime
{
    /**
     * @param ClockInterface $clock The clock.
     */
    public function __construct(
        private ClockInterface $clock,
    ) {
    }

    /**
     * @return string Now.
     */
    public function now(): string
    {
        return $this->clock->now()->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\\TH:i:s\\Z');
    }
}
