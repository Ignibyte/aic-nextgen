<?php

declare(strict_types=1);

namespace AIC\Auth\Domain;

/**
 * Who a sign-in signed in (#238): the user's id, kept in the session, and
 * the fields the app's `UserProvider` read beside the password's hash, so the
 * app needs no second read of the user. Never the hash.
 */
final readonly class SignedInUser
{
    /**
     * @param string                $userId  The user's id.
     * @param array<string, string> $profile What the provider read for the app, by name: a name, a role.
     */
    public function __construct(
        public string $userId,
        public array $profile = [],
    ) {
    }
}
