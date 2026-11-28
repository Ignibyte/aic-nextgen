<?php

declare(strict_types=1);

namespace AIC\Socket\Domain;

/**
 * One frame read off a connection's buffer (#32): its bytes used, or none
 * while the frame is still arriving, or the close code that refuses it.
 */
final readonly class WireFrame
{
    /**
     * A part after the first of a fragmented message.
     */
    public const int CONTINUATION = 0;

    /**
     * A text message, or its first part.
     */
    public const int TEXT = 1;

    /**
     * A binary message, which a Socket does not take.
     */
    public const int BINARY = 2;

    /**
     * The close handshake.
     */
    public const int CLOSE = 8;

    /**
     * A ping, answered with a pong of the same payload.
     */
    public const int PING = 9;

    /**
     * A pong, which needs no answer.
     */
    public const int PONG = 10;

    /**
     * @param int    $used    The bytes it took from the buffer; 0 while it is incomplete or refused.
     * @param bool   $final   Whether it ends its message.
     * @param int    $opcode  Its opcode.
     * @param string $payload Its payload, unmasked.
     * @param int    $refusal The close code that refuses it (1002 malformed, 1009 too large); 0 when it is fine.
     */
    public function __construct(
        public int $used,
        public bool $final = true,
        public int $opcode = self::CONTINUATION,
        public string $payload = '',
        public int $refusal = 0,
    ) {
    }

    /**
     * @return bool Whether it is a control frame (close, ping or pong).
     */
    public function control(): bool
    {
        return $this->opcode >= self::CLOSE;
    }
}
