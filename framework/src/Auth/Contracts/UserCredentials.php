<?php

declare(strict_types=1);

namespace AIC\Auth\Contracts;

/**
 * What the Authenticator needs of one user: who they are, and their
 * password's stored hash; and what else the provider read that the app wants
 * back from a sign-in (#238).
 */
final readonly class UserCredentials
{
    /**
     * @param string                $userId       The user's id, kept in the session at login.
     * @param string                $passwordHash The stored password hash.
     * @param array<string, string> $profile      Fields read with them for the app, by name, handed back by a
     *                                            sign-in as `SignedInUser::$profile`; none by default.
     */
    public function __construct(
        public string $userId,
        public string $passwordHash,
        public array $profile = [],
    ) {
    }
}
