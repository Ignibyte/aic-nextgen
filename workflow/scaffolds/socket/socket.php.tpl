<?php

declare(strict_types=1);

namespace {namespace}\Controller;

use AIC\Broadcast\Application\Broadcaster;
use AIC\Broadcast\Domain\Channel;
use AIC\Controller\Contracts\Socket;
use AIC\Controller\Contracts\SocketFrame;
use AIC\Controller\Contracts\SocketResponse;
use AIC\Controller\Domain\SocketClose;
use AIC\Controller\Domain\SocketReply;
use AIC\Database\Contracts\QueryException;
use InvalidArgumentException;
use Override;

use function explode;
use function trim;

/**
 * {path}, made by bin/aic make:socket (#32): `listen` hears the `{channel}`
 * channel, `send <text>` publishes the text there for every connection that
 * listens, and `bye` closes; anything else is answered `unknown`.
 */
final readonly class {class} implements Socket
{
    /**
     * @param Broadcaster $broadcaster Publishes to the channel.
     */
    public function __construct(
        private Broadcaster $broadcaster,
    ) {
    }

    /**
     * @param SocketFrame $socketFrame The message.
     *
     * @throws QueryException           When the broadcast cannot be written.
     * @throws InvalidArgumentException When the channel's name is invalid.
     *
     * @return SocketReply|SocketClose The answer.
     */
    #[Override]
    public function socket(SocketFrame $socketFrame): SocketResponse
    {
        $words = explode(' ', trim($socketFrame->text), 2);

        return match ($words[0]) {
            'listen' => new SocketReply(['listening'], [new Channel('{channel}')]),
            'send' => $this->send($words[1] ?? ''),
            'bye' => new SocketClose(1_000, 'bye'),
            default => new SocketReply(['unknown']),
        };
    }

    /**
     * @param string $text What to publish.
     *
     * @throws QueryException           When the broadcast cannot be written.
     * @throws InvalidArgumentException When the channel's name is invalid.
     *
     * @return SocketReply Nothing once it is published; `unknown` for no text.
     */
    private function send(string $text): SocketReply
    {
        if ($text === '') {
            return new SocketReply(['unknown']);
        }

        $this->broadcaster->publish(new Channel('{channel}'), $text);

        return new SocketReply();
    }
}
