<?php

declare(strict_types=1);

namespace AIC\Socket\Domain;

use InvalidArgumentException;

/**
 * How the socket server listens (#32): its address, the largest message a
 * frame may carry, the most connections it holds, and how often it reads the
 * broadcasts while no connection sends anything.
 */
final readonly class SocketConfig
{
    /**
     * @param string $host           The address it listens on; a proxy in front routes `/ws/` to it.
     * @param int    $port           Its port.
     * @param int    $maxFrameBytes  The largest payload a client frame may carry; a larger one closes 1009.
     * @param int    $maxConnections The most open connections; one more is refused 503.
     * @param int    $tickMs         The longest wait for a connection before the broadcasts are read again.
     *
     * @throws InvalidArgumentException When a number is out of range.
     */
    public function __construct(
        public string $host = '127.0.0.1',
        public int $port = 8_091,
        public int $maxFrameBytes = 65_536,
        public int $maxConnections = 256,
        public int $tickMs = 250,
    ) {
        if ($port < 1 || $port > 65_535 || $maxFrameBytes < 125 || $maxConnections < 1 || $tickMs < 10) {
            throw new InvalidArgumentException('SocketConfig: a port is 1 to 65535, frames hold 125 bytes or more, '
                . 'one connection or more, a tick of 10 ms or more.');
        }
    }
}
