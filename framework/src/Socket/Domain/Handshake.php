<?php

declare(strict_types=1);

namespace AIC\Socket\Domain;

use function base64_encode;
use function sha1;
use function str_contains;
use function strlen;
use function strtolower;

/**
 * The opening handshake's HTTP side (#32): whether a request asks for a
 * WebSocket, the 101 that accepts it with RFC 6455's accept key, and the
 * plain answer that refuses one.
 */
final readonly class Handshake
{
    /**
     * The GUID RFC 6455 appends to the client's key.
     */
    private const string GUID = '258EAFA5-E914-47DA-95CA-C5AB0DC85B11';

    /**
     * @param UpgradeHead $upgradeHead The request.
     *
     * @return bool Whether it is a version-13 WebSocket upgrade with a key.
     */
    public function asked(UpgradeHead $upgradeHead): bool
    {
        return $upgradeHead->method === 'GET'
            && str_contains(strtolower($upgradeHead->header('upgrade')), 'websocket')
            && $upgradeHead->header('sec-websocket-version') === '13'
            && $upgradeHead->header('sec-websocket-key') !== '';
    }

    /**
     * @param UpgradeHead $upgradeHead An upgrade request.
     *
     * @return string The 101 that accepts it.
     */
    public function accepted(UpgradeHead $upgradeHead): string
    {
        $accept = base64_encode(sha1($upgradeHead->header('sec-websocket-key') . self::GUID, true));

        return "HTTP/1.1 101 Switching Protocols\r\nUpgrade: websocket\r\nConnection: Upgrade\r\n"
            . 'Sec-WebSocket-Accept: ' . $accept . "\r\n\r\n";
    }

    /**
     * @param int    $status A refusal's status: 400, 403 or 404.
     * @param string $reason Its reason phrase and body.
     *
     * @return string The answer, which closes the connection.
     */
    public function refused(int $status, string $reason): string
    {
        return 'HTTP/1.1 ' . $status . ' ' . $reason . "\r\nContent-Type: text/plain\r\nContent-Length: "
            . strlen($reason) . "\r\nConnection: close\r\n\r\n" . $reason;
    }
}
