<?php

declare(strict_types=1);

namespace App\Job\Service;

use AIC\Database\Contracts\QueryException;
use AIC\Schedule\Application\Scheduler;
use AIC\Schedule\Domain\Schedule;

/**
 * The jobs page's schedule lines: each schedule's name, cadence, job and last
 * run, as the page lists them.
 */
final readonly class ScheduleLines
{
    /**
     * @param Scheduler $scheduler Knows when each schedule last fired.
     */
    public function __construct(
        private Scheduler $scheduler,
    ) {
    }

    /**
     * @param list<Schedule> $schedules The app's schedules.
     *
     * @throws QueryException When the ledger cannot be read.
     *
     * @return list<string> One line per schedule, in order.
     */
    public function lines(array $schedules): array
    {
        $lines = [];

        foreach ($schedules as $schedule) {
            $last = $this->scheduler->lastRun($schedule->name);
            $lines[] = $schedule->name . ' (' . $schedule->cadence->expression . ') queues ' . $schedule->job
                . '; last run ' . ($last === '' ? 'never' : $last . ' UTC');
        }

        return $lines;
    }
}
