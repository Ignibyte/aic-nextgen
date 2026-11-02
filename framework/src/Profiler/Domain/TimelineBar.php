<?php

declare(strict_types=1);

namespace AIC\Profiler\Domain;

use AIC\Component\Domain\Timeline;
use AIC\Component\Domain\TimelineSegment;

use function intdiv;
use function max;
use function sprintf;

/**
 * A profile's times as a `Timeline` (#230): the middleware, the handler
 * without its render, the render, and what came after, end to end on the
 * timeline's 0 to 1000 scale.
 */
final readonly class TimelineBar
{
    /**
     * @param ProfileTimes $profileTimes Where the time went.
     *
     * @return Timeline The bar.
     */
    public function timeline(ProfileTimes $profileTimes): Timeline
    {
        $total = max(1, $profileTimes->total);
        $parts = [
            ['Middleware', max(0, $profileTimes->middleware), 'middleware'],
            ['Handler', max(0, $profileTimes->handler - $profileTimes->render), 'handler'],
            ['Render', max(0, $profileTimes->render), 'render'],
            ['After the handler', max(0, $profileTimes->total - $profileTimes->middleware - $profileTimes->handler), 'rest'],
        ];
        $segments = [];
        $start = 0;

        foreach ($parts as [$label, $microseconds, $tone]) {
            $width = intdiv($microseconds * 1_000, $total);
            $segments[] = new TimelineSegment(sprintf('%s: %d µs', $label, $microseconds), (string) $start, (string) $width, $tone);
            $start += $width;
        }

        return new Timeline(sprintf('%d µs in all', $profileTimes->total), $segments);
    }
}
