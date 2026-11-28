<?php

declare(strict_types=1);

namespace AIC\Controller\Domain;

use AIC\Controller\Contracts\SocketResponse;
use AIC\Controller\Contracts\SocketWriter;
use Override;

/**
 * A Socket's answer that ends the connection (#32): a close frame with its
 * code and reason, after which the server drops the connection.
 */
final readonly class SocketClose implements SocketResponse
{
    /**
     * @param int    $code   The close code: 1000 for a normal close, 4000 to 4999 for a module's own.
     * @param string $reason Why, at most 123 bytes.
     */
    public function __construct(
        private int $code = 1_000,
        private string $reason = '',
    ) {
    }

    /**
     * @param SocketWriter $socketWriter Writes to the connection.
     */
    #[Override]
    public function writeTo(SocketWriter $socketWriter): void
    {
        $socketWriter->close($this->code, $this->reason);
    }
}
