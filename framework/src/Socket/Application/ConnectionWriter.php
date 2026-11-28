<?php

declare(strict_types=1);

namespace AIC\Socket\Application;

use AIC\Controller\Contracts\SocketWriter;
use AIC\Socket\Domain\FrameCodec;
use AIC\Socket\Domain\WireFrame;
use Override;

/**
 * A Socket's answer written to the connection that sent the frame (#32):
 * each message one text frame, and a close a close frame after which the
 * connection is dropped.
 */
final readonly class ConnectionWriter implements SocketWriter
{
    /**
     * @param Connections $connections The connections.
     * @param int         $id          The one answered.
     * @param FrameCodec  $frameCodec  Writes the frames.
     */
    public function __construct(
        private Connections $connections,
        private int $id,
        private FrameCodec $frameCodec = new FrameCodec(),
    ) {
    }

    /**
     * @param string $text A text message.
     */
    #[Override]
    public function send(string $text): void
    {
        $this->connections->write($this->id, $this->frameCodec->encode(WireFrame::TEXT, $text));
    }

    /**
     * @param list<string> $channels The channels the connection hears from now on.
     */
    #[Override]
    public function listen(array $channels): void
    {
        $this->connections->listen($this->id, $channels);
    }

    /**
     * @param int    $code   The close code.
     * @param string $reason Why.
     */
    #[Override]
    public function close(int $code, string $reason): void
    {
        $this->connections->write($this->id, $this->frameCodec->close($code, $reason));
        $this->connections->drop($this->id);
    }
}
