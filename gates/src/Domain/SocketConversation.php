<?php

declare(strict_types=1);

namespace Gates\Domain;

/**
 * One connection of the native site script's `sockets` (gate:23, #32): an upgrade, then frames sent and read, played
 * against `bin/aic socket:serve` and the compiled socket server, whose transcripts must match.
 */
final readonly class SocketConversation
{
    /**
     * @param string           $name       What it checks, for the report.
     * @param string           $path       The socket's path.
     * @param bool             $sameOrigin Whether the upgrade's `Origin` is the server's own; false sends another site's.
     * @param int              $status     The upgrade's status both servers must answer: 101, or a refusal.
     * @param list<SocketSend> $sends      The frames sent after a 101, in order.
     */
    public function __construct(
        public string $name,
        public string $path,
        public bool $sameOrigin,
        public int $status,
        public array $sends,
    ) {
    }
}
