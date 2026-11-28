<?php

declare(strict_types=1);

namespace AIC\Controller\Domain;

use AIC\Broadcast\Domain\Channel;
use AIC\Controller\Contracts\SocketResponse;
use AIC\Controller\Contracts\SocketWriter;
use Override;

/**
 * A Socket's answer to the connection that sent the frame (#32): messages,
 * none, one or several, each its own text frame, in order; and, when given,
 * the broadcast channels the connection hears from then on (#314's), which
 * replace the ones before, so a socket moves a connection between rooms.
 */
final readonly class SocketReply implements SocketResponse
{
    /**
     * @param list<string>       $messages The messages; empty to answer nothing.
     * @param list<Channel>|null $channels The channels the connection hears from now on; null to keep its own.
     */
    public function __construct(
        private array $messages = [],
        private ?array $channels = null,
    ) {
    }

    /**
     * @param SocketWriter $socketWriter Writes to the connection.
     */
    #[Override]
    public function writeTo(SocketWriter $socketWriter): void
    {
        foreach ($this->messages as $message) {
            $socketWriter->send($message);
        }

        if ($this->channels === null) {
            return;
        }

        $names = [];

        foreach ($this->channels as $channel) {
            $names[] = $channel->name;
        }

        $socketWriter->listen($names);
    }
}
