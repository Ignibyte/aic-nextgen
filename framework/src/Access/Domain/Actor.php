<?php

declare(strict_types=1);

namespace AIC\Access\Domain;

use function in_array;

/**
 * Who asks (#304): the signed-in user's id, empty for a visitor, and every
 * permission the roles assigned to that user grant. There is no user entity:
 * the id is what the app's `UserProvider` signed in.
 */
final readonly class Actor
{
    /**
     * What a route needs when any signed-in user may open it.
     */
    public const string SIGNED_IN = 'signed-in';

    /**
     * @param string       $userId      The signed-in user's id; empty for a visitor.
     * @param list<string> $permissions What the user's roles grant.
     */
    public function __construct(
        public string $userId,
        public array $permissions,
    ) {
    }

    /**
     * @return bool Whether a user is signed in.
     */
    public function signedIn(): bool
    {
        return $this->userId !== '';
    }

    /**
     * @param string $permission A permission, such as `notes.delete-pinned`, or `signed-in`.
     *
     * @return bool Whether the actor is signed in and holds it; `signed-in` asks only the first.
     */
    public function can(string $permission): bool
    {
        return $this->signedIn()
            && ($permission === self::SIGNED_IN || in_array($permission, $this->permissions, true));
    }
}
