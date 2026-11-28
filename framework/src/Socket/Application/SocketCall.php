<?php

declare(strict_types=1);

namespace AIC\Socket\Application;

use AIC\Controller\Contracts\SocketFrame;
use AIC\Socket\Domain\FrameCodec;
use AIC\Socket\Domain\SocketEntry;
use Throwable;

use function mb_check_encoding;

/**
 * One whole text message handed to its connection's socket (#32), and the
 * answer written back. Text that is not UTF-8 closes the connection 1007, as
 * RFC 6455 asks; a socket that throws closes it 1011, and the server goes on
 * serving the others.
 */
final readonly class SocketCall
{
    /**
     * @param FrameCodec $frameCodec Writes the closes.
     */
    public function __construct(
        private FrameCodec $frameCodec = new FrameCodec(),
    ) {
    }

    /**
     * @param int                        $id          An opened connection.
     * @param Connections                $connections The connections.
     * @param array<string, SocketEntry> $entries     Every declared socket by path.
     * @param string                     $text        The message.
     */
    public function call(int $id, Connections $connections, array $entries, string $text): void
    {
        $path = $connections->opened()[$id] ?? '';
        $entry = $entries[$path] ?? null;

        if (!$entry instanceof SocketEntry || !mb_check_encoding($text, 'UTF-8')) {
            $this->shut($id, $connections, 1_007);

            return;
        }

        try {
            $entry->socket->socket(new SocketFrame($path, (string) $id, $connections->user($id), $text))
                ->writeTo(new ConnectionWriter($connections, $id));
        } catch (Throwable) {
            $this->shut($id, $connections, 1_011);
        }
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
