<?php

declare(strict_types=1);

namespace AIC\Controller\Contracts;

/**
 * A text message one connection sent to a Socket (#32), with who sent it:
 * the user the upgrade request's session held, read once when the connection
 * opened.
 */
final readonly class SocketFrame
{
    /**
     * @param string $path       The socket's path, such as `/ws/ledger`.
     * @param string $connection The connection's id, unique while the server runs.
     * @param string $user       The signed-in user's id; empty for a visitor.
     * @param string $text       The message, valid UTF-8 as RFC 6455 requires of text.
     */
    public function __construct(
        public string $path,
        public string $connection,
        public string $user,
        public string $text,
    ) {
    }
}
