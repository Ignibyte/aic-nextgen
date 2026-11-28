<?php

declare(strict_types=1);

namespace AIC\Socket\Domain;

use AIC\Controller\Contracts\Socket;

/**
 * A declared socket as the server serves it (#32): the Socket the generated
 * wiring built and who may open a connection to it.
 */
final readonly class SocketEntry
{
    /**
     * @param Socket $socket The module's Socket.
     * @param string $needs  A permission or `signed-in`, checked when a connection opens; empty for anyone.
     */
    public function __construct(
        public Socket $socket,
        public string $needs = '',
    ) {
    }
}
