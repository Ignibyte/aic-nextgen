<?php

declare(strict_types=1);

namespace AIC\Controller\Contracts;

/**
 * Where an event stream's events come from, pulled one at a time as the
 * response is sent. `next()` may wait for the next event; the event marked
 * last ends the stream once it is sent.
 */
interface EventSource
{
    /**
     * @return ServerEvent The next event.
     */
    public function next(): ServerEvent;
}
