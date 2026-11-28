<?php

declare(strict_types=1);

namespace AIC\Controller\Contracts;

/**
 * The Socket kind (#32): a two-way connection's entry point. The socket
 * server calls it with each text message a connection sends, and its answer
 * is messages back to that connection or a close; what goes to every other
 * connection is a broadcast on the socket's channel.
 *
 * Every entry point is exactly one kind, and each kind returns only its own
 * sealed response type; the phpat guards in gates/architecture hold both.
 */
interface Socket
{
    /**
     * @param SocketFrame $socketFrame The message and who sent it.
     *
     * @return SocketResponse The answer.
     */
    public function socket(SocketFrame $socketFrame): SocketResponse;
}
