<?php

declare(strict_types=1);

namespace AIC\Session\Contracts;

/**
 * One visitor's session (#41): string values by key, kept between requests
 * behind a cookie. A session left empty at the end of a request is not kept.
 * Its id stays with SessionMiddleware; `remove` and `regenerateId` join with
 * their callers (#42, #9).
 */
interface Session
{
    /**
     * @param string $key A key.
     *
     * @return string|null Its value; null when unset.
     */
    public function get(string $key): ?string;

    /**
     * @param string $key   A key.
     * @param string $value Its new value.
     */
    public function set(string $key, string $value): void;

    /**
     * Unsets every value, so the session ends with the request.
     */
    public function clear(): void;
}
