<?php

declare(strict_types=1);

namespace AIC\Controller\Contracts;

/**
 * Writes a Socket's answer to its connection (#32). The socket server
 * implements it over the connection's stream.
 */
interface SocketWriter
{
    /**
     * @param string $text A text message, sent as one frame.
     */
    public function send(string $text): void;

    /**
     * @param list<string> $channels The broadcast channels the connection hears from now on, in place of the ones
     *                               before; empty for none.
     */
    public function listen(array $channels): void;

    /**
     * @param int    $code   The close code (RFC 6455, such as 1000 or a module's own from 4000).
     * @param string $reason Why, at most 123 bytes.
     */
    public function close(int $code, string $reason): void;
}
