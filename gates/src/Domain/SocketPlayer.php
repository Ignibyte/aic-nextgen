<?php

declare(strict_types=1);

namespace Gates\Domain;

/**
 * Plays one socket conversation against a local socket server (gate:23, #32).
 */
interface SocketPlayer
{
    /**
     * @param int                $port               The server's port on 127.0.0.1.
     * @param SocketConversation $socketConversation What to send and read.
     *
     * @return list<string> The transcript: the upgrade's status line, then each frame read as `text:<data>`,
     *                      `pong:<data>` or `close:<code>:<reason>`, `timeout` for one that did not come and `eof` for
     *                      a connection the server ended.
     */
    public function play(int $port, SocketConversation $socketConversation): array;
}
