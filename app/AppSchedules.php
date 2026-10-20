<?php

declare(strict_types=1);

namespace App;

use AIC\Schedule\Domain\Cadence;
use AIC\Schedule\Domain\Schedule;
use InvalidArgumentException;

/**
 * The app's schedules (#47), queued by `bin/aic schedule:run`; the demo's
 * queues its job every minute.
 */
final readonly class AppSchedules
{
    /**
     * @throws InvalidArgumentException When a cadence is malformed.
     *
     * @return list<Schedule> The schedules.
     */
    public function all(): array
    {
        return [new Schedule('demo-every-minute', 'demo', ['mode' => 'succeed'], new Cadence('* * * * *'))];
    }
}
