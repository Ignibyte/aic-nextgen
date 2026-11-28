<?php

declare(strict_types=1);

namespace AIC\Socket\Domain;

/**
 * How an upgrade request is answered (#32): 101 with the user it opens for,
 * or the status and reason that refuse it.
 */
final readonly class Admission
{
    /**
     * @param int    $status 101 to open the connection; 400, 403, 404 or 503 to refuse it.
     * @param string $reason The status's reason phrase.
     * @param string $user   The signed-in user's id for an opened connection; empty for a visitor.
     */
    public function __construct(
        public int $status,
        public string $reason,
        public string $user = '',
    ) {
    }
}
