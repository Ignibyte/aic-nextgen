<?php

declare(strict_types=1);

namespace AIC\Broadcast\Contracts;

/**
 * One published event (#314), as a row of `aic_broadcasts` holds it: its id,
 * which a stream sends as the event's id and a reconnect sends back as
 * `Last-Event-ID`, and its data. The channel is the stream's.
 */
final readonly class Broadcast
{
    /**
     * @param int    $id   Its id, rising in the order events were published.
     * @param string $data Its data, as the publisher wrote it.
     */
    public function __construct(
        public int $id,
        public string $data,
    ) {
    }
}
