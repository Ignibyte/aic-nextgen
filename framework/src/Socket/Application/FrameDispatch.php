<?php

declare(strict_types=1);

namespace AIC\Socket\Application;

use AIC\Socket\Domain\FrameCodec;
use AIC\Socket\Domain\SocketConfig;
use AIC\Socket\Domain\SocketEntry;
use AIC\Socket\Domain\WireFrame;

use function strlen;

/**
 * Every whole frame an opened connection sent (#32), in order: a ping is
 * answered with a pong, a close with a close and the connection is dropped,
 * a text message (whole or in parts) goes to its socket, and a binary frame,
 * a malformed one or one past the cap closes the connection with its code.
 */
final readonly class FrameDispatch
{
    /**
     * @param SocketConfig $socketConfig The largest message.
     * @param SocketCall   $socketCall   Hands a message to its socket.
     * @param FrameCodec   $frameCodec   Reads and writes frames.
     */
    public function __construct(
        private SocketConfig $socketConfig,
        private SocketCall $socketCall,
        private FrameCodec $frameCodec = new FrameCodec(),
    ) {
    }

    /**
     * @param int                        $id          An opened connection.
     * @param Connections                $connections The connections.
     * @param array<string, SocketEntry> $entries     Every declared socket by path.
     */
    public function drain(int $id, Connections $connections, array $entries): void
    {
        $more = true;

        while ($more) {
            $more = $this->next($id, $connections, $entries);
        }
    }

    /**
     * @param int                        $id          An opened connection.
     * @param Connections                $connections The connections.
     * @param array<string, SocketEntry> $entries     Every declared socket by path.
     *
     * @return bool Whether a frame was taken and the connection is still open.
     */
    private function next(int $id, Connections $connections, array $entries): bool
    {
        $wireFrame = $this->frameCodec->decode($connections->buffer($id), $this->socketConfig->maxFrameBytes);

        if ($wireFrame->refusal !== 0) {
            $this->shut($id, $connections, $wireFrame->refusal);

            return false;
        }

        if ($wireFrame->used === 0) {
            return false;
        }

        $connections->consume($id, $wireFrame->used);
        $wireFrame->control()
            ? $this->controlled($id, $connections, $wireFrame)
            : $this->message($id, $connections, $entries, $wireFrame);

        return $connections->has($id);
    }

    /**
     * @param int         $id          An opened connection.
     * @param Connections $connections The connections.
     * @param WireFrame   $wireFrame   A close, ping or pong.
     */
    private function controlled(int $id, Connections $connections, WireFrame $wireFrame): void
    {
        if (!$wireFrame->final || strlen($wireFrame->payload) > 125) {
            $this->shut($id, $connections, 1_002);

            return;
        }

        if ($wireFrame->opcode === WireFrame::PING) {
            $connections->write($id, $this->frameCodec->encode(WireFrame::PONG, $wireFrame->payload));
        }

        if ($wireFrame->opcode !== WireFrame::CLOSE) {
            return;
        }

        $connections->write($id, $this->frameCodec->encode(WireFrame::CLOSE, $wireFrame->payload));
        $connections->drop($id);
    }

    /**
     * @param int                        $id          An opened connection.
     * @param Connections                $connections The connections.
     * @param array<string, SocketEntry> $entries     Every declared socket by path.
     * @param WireFrame                  $wireFrame   A text, binary or continuation frame.
     */
    private function message(int $id, Connections $connections, array $entries, WireFrame $wireFrame): void
    {
        $part = $connections->part($id);
        $code = $this->refusal($wireFrame, $part);

        if ($code !== 0) {
            $this->shut($id, $connections, $code);

            return;
        }

        $text = ($part ?? '') . $wireFrame->payload;
        $connections->keep($id, $wireFrame->final ? null : $text);

        if (!$wireFrame->final) {
            return;
        }

        $this->socketCall->call($id, $connections, $entries, $text);
    }

    /**
     * @param WireFrame   $wireFrame A data frame.
     * @param string|null $part      The text so far of a message in parts; null when none is arriving.
     *
     * @return int The close code that refuses it; 0 when it is fine.
     */
    private function refusal(WireFrame $wireFrame, ?string $part): int
    {
        if ($wireFrame->opcode === WireFrame::BINARY) {
            return 1_003;
        }

        if (($wireFrame->opcode === WireFrame::CONTINUATION) === ($part === null)) {
            return 1_002;
        }

        return strlen($part ?? '') + strlen($wireFrame->payload) > $this->socketConfig->maxFrameBytes
            ? 1_009
            : 0;
    }

    /**
     * @param int         $id          A connection.
     * @param Connections $connections The connections.
     * @param int         $code        The close code.
     */
    private function shut(int $id, Connections $connections, int $code): void
    {
        $connections->write($id, $this->frameCodec->close($code, ''));
        $connections->drop($id);
    }
}
