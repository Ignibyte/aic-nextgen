<?php

declare(strict_types=1);

namespace AIC\Session\Contracts;

/**
 * Where sessions live between requests, as payloads by id.
 */
interface SessionStore
{
    /**
     * @param string $id A session id.
     *
     * @return string|null Its payload; null when there is none or it has expired.
     */
    public function read(string $id): ?string;

    /**
     * @param string $id      A session id.
     * @param string $payload Its payload.
     * @param int    $ttl     Seconds it lives from now.
     */
    public function write(string $id, string $payload, int $ttl): void;

    /**
     * Extends a session's life and leaves its payload alone (#207): what a request that changed nothing does.
     *
     * @param string $id  A session id.
     * @param int    $ttl Seconds it lives from now.
     */
    public function touch(string $id, int $ttl): void;

    /**
     * @param string $id A session id to forget.
     */
    public function destroy(string $id): void;
}
