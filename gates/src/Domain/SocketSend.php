<?php

declare(strict_types=1);

namespace Gates\Domain;

/**
 * One frame a socket conversation sends (gate:23, #32), and how many frames it then reads back.
 */
final readonly class SocketSend
{
    /**
     * @param string $frame `text`, `binary` or `ping`.
     * @param string $data  The frame's payload.
     * @param int    $read  How many frames to read after it, each within the client's wait.
     */
    public function __construct(
        public string $frame,
        public string $data,
        public int $read,
    ) {
    }
}
