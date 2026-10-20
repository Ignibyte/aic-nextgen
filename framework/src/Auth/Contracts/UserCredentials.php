<?php

declare(strict_types=1);

namespace AIC\Auth\Contracts;

/**
 * What the Authenticator needs of one user: who they are, and their
 * password's stored hash.
 */
final readonly class UserCredentials
{
    /**
     * @param string $userId       The user's id, kept in the session at login.
     * @param string $passwordHash The stored password hash.
     */
    public function __construct(
        public string $userId,
        public string $passwordHash,
    ) {
    }
}
