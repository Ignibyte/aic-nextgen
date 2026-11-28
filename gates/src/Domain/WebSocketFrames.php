<?php

declare(strict_types=1);

namespace Gates\Domain;

use function array_key_exists;
use function chr;
use function is_array;
use function is_int;
use function ord;
use function pack;
use function strlen;
use function substr;
use function unpack;

/**
 * The client's half of RFC 6455 framing for gate:23's socket conversations (#32): a masked frame out, a server's
 * unmasked frame in as a transcript entry.
 */
final readonly class WebSocketFrames
{
    /**
     * The mask every frame the gate sends uses; a fixed key keeps the bytes the same on both passes.
     */
    private const string MASK = "\x12\x34\x56\x78";

    /**
     * The opcodes a conversation sends, by frame name.
     */
    private const array OPCODES = ['text' => 0x1, 'binary' => 0x2, 'ping' => 0x9];

    /**
     * @param string $frame `text`, `binary` or `ping`.
     * @param string $data  The payload, under 65 536 bytes.
     *
     * @return string The frame, final and masked.
     */
    public function masked(string $frame, string $data): string
    {
        $length = strlen($data);
        $head = chr(0x80 | (self::OPCODES[$frame] ?? 0x1));
        $head .= $length < 126
            ? chr(0x80 | $length)
            : chr(0x80 | 126) . pack('n', $length);
        $masked = '';

        for ($index = 0; $index < $length; $index += 1) {
            $masked .= chr(ord($data[$index]) ^ ord(self::MASK[$index % 4]));
        }

        return $head . self::MASK . $masked;
    }

    /**
     * @param string $buffer What the server has sent so far, after the upgrade.
     *
     * @return array{string, int}|null The first frame as `text:<data>`, `pong:<data>`, `close:<code>:<reason>` or
     *                                 `op<n>:<data>`, and the bytes it took; null while it is incomplete.
     */
    public function decoded(string $buffer): ?array
    {
        if (strlen($buffer) < 2) {
            return null;
        }

        [$length, $start] = self::length($buffer);

        return $start > 0 && strlen($buffer) >= $start + $length
            ? [self::entry(ord($buffer[0]) & 0x0F, substr($buffer, $start, $length)), $start + $length]
            : null;
    }

    /**
     * @param string $buffer At least a frame's first two bytes.
     *
     * @return array{int, int} The payload's length and where it starts; a start of 0 while the length is incomplete.
     */
    private static function length(string $buffer): array
    {
        $short = ord($buffer[1]) & 0x7F;
        $bytes = match ($short) {
            126 => 2,
            127 => 8,
            default => 0,
        };

        if (strlen($buffer) < 2 + $bytes) {
            return [0, 0];
        }

        $length = match ($bytes) {
            2 => self::number('n', substr($buffer, 2, 2)),
            8 => self::number('J', substr($buffer, 2, 8)),
            default => $short,
        };

        return [$length, 2 + $bytes];
    }

    /**
     * @param string $format An unpack format of one unsigned number: `n` or `J`.
     * @param string $bytes  Its bytes.
     *
     * @return int The number; 0 when the bytes hold none.
     */
    private static function number(string $format, string $bytes): int
    {
        $values = unpack($format, $bytes);

        return is_array($values) && array_key_exists(1, $values) && is_int($values[1])
            ? $values[1]
            : 0;
    }

    /**
     * @param int    $opcode  The frame's opcode.
     * @param string $payload Its payload.
     *
     * @return string Its transcript entry.
     */
    private static function entry(int $opcode, string $payload): string
    {
        return match ($opcode) {
            0x1 => 'text:' . $payload,
            0xA => 'pong:' . $payload,
            0x8 => 'close:' . self::number('n', substr($payload, 0, 2) . "\0\0") . ':' . substr($payload, 2),
            default => 'op' . $opcode . ':' . $payload,
        };
    }
}
