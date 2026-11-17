<?php

declare(strict_types=1);

namespace AIC\HttpClient\Contracts;

/**
 * Sends many requests at once (#305) and waits for them all: one slow upstream
 * costs the time of the slowest, not the sum.
 */
interface RequestPool
{
    /**
     * @param array<string, PooledRequest> $requests The requests, each under a name.
     *
     * @throws RequestException When a request cannot be sent at all; none is then sent.
     *
     * @return array<string, PooledAnswer> What each got, under its name, in the order given.
     */
    public function send(array $requests): array;
}
