<?php

declare(strict_types=1);

namespace AIC\Socket\Domain;

use function chr;
use function ord;
use function strlen;
use function substr;

/**
 * RFC 6455 framing (#32): a server frame is never masked, and a client frame
 * must be. A length takes 7, 16 or 64 bits; a frame past the cap is refused
 * as soon as its header shows its length, before its payload arrives.
 */
final readonly class FrameCodec
{
    /**
     * @param int    $opcode  The opcode (`WireFrame::TEXT`, `CLOSE`, `PONG`).
     * @param string $payload The payload.
     *
     * @return string The frame, final and unmasked.
     */
    public function encode(int $opcode, string $payload): string
    {
        return chr((0x80 | $opcode) & 255) . $this->length(strlen($payload)) . $payload;
    }

    /**
     * @param int    $code   A close code.
     * @param string $reason Why.
     *
     * @return string A close frame carrying them.
     */
    public function close(int $code, string $reason): string
    {
        return $this->encode(WireFrame::CLOSE, chr(($code >> 8) & 255) . chr($code & 255) . substr($reason, 0, 123));
    }

    /**
     * @param string $buffer   What the connection sent and no frame has taken yet.
     * @param int    $maxBytes The largest payload a frame may carry.
     *
     * @return WireFrame The first frame in it; one that used nothing while it is incomplete.
     */
    public function decode(string $buffer, int $maxBytes): WireFrame
    {
        if (strlen($buffer) < 2) {
            return new WireFrame(0);
        }

        $first = ord($buffer[0]);
        $second = ord($buffer[1]);

        if (($first & 0x70) !== 0 || ($second & 0x80) === 0) {
            return new WireFrame(0, refusal: 1_002);
        }

        return $this->sized($buffer, $first, $this->header($buffer, $second & 127), $maxBytes);
    }

    /**
     * @param string    $buffer   The buffer.
     * @param int       $first    Its first byte: the final bit and the opcode.
     * @param list<int> $header   The payload's length and where its mask starts; the start is 0 while the header
     *                            is incomplete.
     * @param int       $maxBytes The cap.
     *
     * @return WireFrame The frame, incomplete or refused.
     */
    private function sized(string $buffer, int $first, array $header, int $maxBytes): WireFrame
    {
        $length = $header[0] ?? 0;
        $start = $header[1] ?? 0;

        if ($start === 0) {
            return new WireFrame(0);
        }

        if ($length < 0 || $length > $maxBytes) {
            return new WireFrame(0, refusal: 1_009);
        }

        $end = $start + 4 + $length;

        return strlen($buffer) < $end
            ? new WireFrame(0)
            : new WireFrame($end, ($first & 0x80) !== 0, $first & 15, $this->unmasked($buffer, $start, $length));
    }

    /**
     * @param string $buffer The buffer, holding at least its first two bytes.
     * @param int    $code   The second byte's length: the length, or 126 or 127 for a longer one after it.
     *
     * @return list<int> The payload's length and where the mask starts; the start is 0 while the header is incomplete.
     */
    private function header(string $buffer, int $code): array
    {
        $extra = 0;

        if ($code === 126) {
            $extra = 2;
        }

        if ($code === 127) {
            $extra = 8;
        }

        if (strlen($buffer) < 2 + $extra) {
            return [0, 0];
        }

        return $extra === 0
            ? [$code, 2]
            : [$this->number(substr($buffer, 2, $extra)), 2 + $extra];
    }

    /**
     * @param string $buffer The buffer.
     * @param int    $start  Where the mask starts.
     * @param int    $length The payload's length.
     *
     * @return string The payload, unmasked.
     */
    private function unmasked(string $buffer, int $start, int $length): string
    {
        $mask = substr($buffer, $start, 4);
        $masked = substr($buffer, $start + 4, $length);
        $payload = '';

        for ($index = 0; $index < $length; $index += 1) {
            $payload .= chr(ord($masked[$index]) ^ ord($mask[$index % 4]));
        }

        return $payload;
    }

    /**
     * @param int $length A payload's length.
     *
     * @return string Its length bytes in a server frame.
     */
    private function length(int $length): string
    {
        if ($length < 126) {
            return chr($length & 127);
        }

        if ($length < 65_536) {
            return chr(126) . chr($length >> 8) . chr($length & 255);
        }

        $bytes = '';

        for ($shift = 56; $shift >= 0; $shift -= 8) {
            $bytes .= chr(($length >> $shift) & 255);
        }

        return chr(127) . $bytes;
    }

    /**
     * @param string $bytes Big-endian bytes.
     *
     * @return int Their number; negative when the top bit of eight bytes is set.
     */
    private function number(string $bytes): int
    {
        $number = 0;

        for ($index = 0, $count = strlen($bytes); $index < $count; $index += 1) {
            $number = ($number << 8) | ord($bytes[$index]);
        }

        return $number;
    }
}
