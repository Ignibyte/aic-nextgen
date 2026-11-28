<?php

declare(strict_types=1);

namespace AIC\Controller\Contracts;

/**
 * What a Socket returns (#32). Sealed: only the answer shapes in
 * AIC\Controller\Domain implement it (a phpat guard), and each writes itself
 * through the SocketWriter.
 */
interface SocketResponse
{
    /**
     * @param SocketWriter $socketWriter Writes to the connection.
     */
    public function writeTo(SocketWriter $socketWriter): void;
}
