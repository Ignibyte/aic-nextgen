<?php

declare(strict_types=1);

namespace AIC\Auth\Contracts;

use SensitiveParameter;

/**
 * Hashes passwords and checks them against a stored hash (#9).
 */
interface PasswordHasher
{
    /**
     * @param string $password A password.
     *
     * @return string Its hash, salted, to store.
     */
    public function hash(#[SensitiveParameter]
        string $password, ): string;

    /**
     * @param string $password A password.
     * @param string $hash     A stored hash.
     *
     * @return bool Whether the password is the hash's.
     */
    public function verify(#[SensitiveParameter]
        string $password, string $hash, ): bool;
}
