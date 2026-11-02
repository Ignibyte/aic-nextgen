<?php

declare(strict_types=1);

namespace AIC\Kernel\Domain;

use function hrtime;
use function intdiv;
use function is_int;

/**
 * The monotonic clock the profiler's seams time with (#229): microseconds from
 * an arbitrary start, so only differences mean anything. It never goes back
 * when the wall clock is set.
 */
final readonly class Monotonic
{
    /**
     * @return int Microseconds since an arbitrary start; 0 where the platform has no monotonic clock.
     */
    public function microseconds(): int
    {
        $nanoseconds = hrtime(true);

        return is_int($nanoseconds)
            ? intdiv($nanoseconds, 1_000)
            : 0;
    }
}
