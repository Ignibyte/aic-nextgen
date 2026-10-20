<?php

declare(strict_types=1);

namespace App\Site;

use AIC\Controller\Contracts\EventSource;
use AIC\Controller\Contracts\ServerEvent;
use Override;

use function usleep;

/**
 * The demo event source behind `/events`: `tick` events numbered from 1, a
 * fifth of a second apart, the last one ending the stream.
 */
final class TickSource implements EventSource
{
    /**
     * The ticks given so far.
     */
    private int $given = 0;

    /**
     * @param int $count How many ticks, 1 or more.
     */
    public function __construct(
        private readonly int $count,
    ) {
    }

    /**
     * @return ServerEvent The next tick, after a pause unless it is the first.
     */
    #[Override]
    public function next(): ServerEvent
    {
        if ($this->given > 0) {
            usleep(200_000);
        }

        $this->given += 1;

        return new ServerEvent('tick', 'tick ' . $this->given, $this->given >= $this->count);
    }
}
