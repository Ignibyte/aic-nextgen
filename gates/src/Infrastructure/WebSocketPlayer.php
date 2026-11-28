<?php

declare(strict_types=1);

namespace Gates\Infrastructure;

use Gates\Domain\SocketConversation;
use Gates\Domain\SocketPlayer;
use Gates\Domain\WebSocketFrames;
use Override;

use function base64_encode;
use function explode;
use function fclose;
use function feof;
use function fread;
use function fwrite;
use function is_resource;
use function microtime;
use function restore_error_handler;
use function set_error_handler;
use function str_contains;
use function stream_set_timeout;
use function stream_socket_client;
use function strpos;
use function substr;

/**
 * Plays a socket conversation over a plain TCP connection to 127.0.0.1 (gate:23, #32): the upgrade with a fixed key,
 * then each frame masked, then the frames the server sends back, each awaited for at most {@see self::WAIT} seconds.
 */
final readonly class WebSocketPlayer implements SocketPlayer
{
    /**
     * Seconds to wait for the upgrade's answer or for one frame.
     */
    private const float WAIT = 3.0;

    /**
     * The 16 bytes whose base64 is the upgrade's `Sec-WebSocket-Key` (RFC 6455 asks only for 16 bytes).
     */
    private const string NONCE = 'aic-gate-socket!';

    /**
     * @param WebSocketFrames $webSocketFrames Encodes and decodes frames.
     */
    public function __construct(
        private WebSocketFrames $webSocketFrames = new WebSocketFrames(),
    ) {
    }

    /**
     * @param int                $port               The server's port on 127.0.0.1.
     * @param SocketConversation $socketConversation What to send and read.
     *
     * @return list<string> The transcript.
     */
    #[Override]
    public function play(int $port, SocketConversation $socketConversation): array
    {
        set_error_handler(static fn (): bool => true);
        $stream = stream_socket_client('tcp://127.0.0.1:' . $port, timeout: self::WAIT);
        restore_error_handler();

        if (!is_resource($stream)) {
            return ['unreachable'];
        }

        stream_set_timeout($stream, (int) self::WAIT);
        fwrite($stream, self::upgrade($port, $socketConversation));
        [$status, $buffer] = $this->head($stream);
        $transcript = $status === 'eof' || !str_contains($status, ' 101 ')
            ? [$status]
            : $this->talk($stream, $socketConversation, $buffer, [$status]);
        fclose($stream);

        return $transcript;
    }

    /**
     * @param resource           $stream             The connection.
     * @param SocketConversation $socketConversation The frames to send.
     * @param string             $buffer             What arrived after the upgrade's head.
     * @param list<string>       $transcript         The transcript so far.
     *
     * @return list<string> The transcript with every frame read.
     */
    private function talk(
        mixed $stream,
        SocketConversation $socketConversation,
        string $buffer,
        array $transcript,
    ): array {
        foreach ($socketConversation->sends as $socketSend) {
            fwrite($stream, $this->webSocketFrames->masked($socketSend->frame, $socketSend->data));

            for ($read = 0; $read < $socketSend->read; $read += 1) {
                [$entry, $buffer] = $this->frame($stream, $buffer);
                $transcript[] = $entry;
            }
        }

        return $transcript;
    }

    /**
     * @param resource $stream The connection.
     * @param string   $buffer What has arrived and is not yet read.
     *
     * @return array{string, string} The next frame's entry (`timeout` or `eof` when none came), and what is left.
     */
    private function frame(mixed $stream, string $buffer): array
    {
        $until = microtime(true) + self::WAIT;

        while (microtime(true) < $until) {
            $decoded = $this->webSocketFrames->decoded($buffer);

            if ($decoded !== null) {
                return [$decoded[0], substr($buffer, $decoded[1])];
            }

            $chunk = self::chunk($stream);

            if ($chunk === null) {
                return ['eof', $buffer];
            }

            $buffer .= $chunk;
        }

        return ['timeout', $buffer];
    }

    /**
     * @param resource $stream The connection.
     *
     * @return array{string, string} The status line (`eof` when the server ended or sent no head in time), and the
     *                               bytes after the head.
     */
    private function head(mixed $stream): array
    {
        $buffer = '';
        $until = microtime(true) + self::WAIT;

        while (!str_contains($buffer, "\r\n\r\n")   && microtime(true) < $until) {
            $chunk = self::chunk($stream);

            if ($chunk === null) {
                break;
            }

            $buffer .= $chunk;
        }

        $end = strpos($buffer, "\r\n\r\n");

        return $end === false
            ? ['eof', '']
            : [explode("\r\n", $buffer, 2)[0], substr($buffer, $end + 4)];
    }

    /**
     * @param resource $stream The connection.
     *
     * @return string|null What arrived within the stream's timeout (possibly nothing); null once the server ended it.
     */
    private static function chunk(mixed $stream): ?string
    {
        $chunk = fread($stream, 65_536);

        return $chunk === false || ($chunk === '' && feof($stream))
            ? null
            : $chunk;
    }

    /**
     * @param int                $port               The server's port.
     * @param SocketConversation $socketConversation Its path and origin.
     *
     * @return string The upgrade request.
     */
    private static function upgrade(int $port, SocketConversation $socketConversation): string
    {
        $host = '127.0.0.1:' . $port;
        $origin = $socketConversation->sameOrigin
            ? 'http://' . $host
            : 'http://elsewhere.invalid';

        return 'GET ' . $socketConversation->path . " HTTP/1.1\r\nHost: " . $host . "\r\nOrigin: " . $origin
            . "\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Key: "
            . base64_encode(self::NONCE) . "\r\nSec-WebSocket-Version: 13\r\n\r\n";
    }
}
