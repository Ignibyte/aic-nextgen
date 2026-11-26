<?php

declare(strict_types=1);

namespace AIC\Access\Domain;

use function in_array;

/**
 * Who asks (#304): the signed-in user's id, empty for a visitor, and every
 * permission the roles assigned to that user grant. There is no user entity:
 * the id is what the app's `UserProvider` signed in. A request with a bearer
 * token (#313) is the token's caller instead: the token's name (or its OAuth
 * client, #331) as its client and its scopes as its permissions; an unknown
 * token is a bearer caller with no client. A token issued by the
 * authorization code grant acts for the person who allowed it and carries
 * their id, but a token never counts as signed in.
 */
final readonly class Actor
{
    /**
     * What a route needs when any signed-in user may open it.
     */
    public const string SIGNED_IN = 'signed-in';

    /**
     * @param string       $userId      The signed-in user's id, or the person a code-flow token acts for (#331);
     *                                  empty for a visitor and any other token's caller.
     * @param list<string> $permissions What the user's roles grant, or the token's scopes.
     * @param string       $client      The bearer token's name; empty without one, or for an unknown token.
     * @param bool         $bearer      Whether the request carried a bearer token, known or not.
     */
    public function __construct(
        public string $userId,
        public array $permissions,
        public string $client = '',
        public bool $bearer = false,
    ) {
    }

    /**
     * @return bool Whether a user is signed in; never for a bearer request.
     */
    public function signedIn(): bool
    {
        return $this->userId !== '' && !$this->bearer;
    }

    /**
     * @return bool Whether the request carried a bearer token no stored token matches.
     */
    public function refused(): bool
    {
        return $this->bearer && $this->client === '';
    }

    /**
     * @param string $permission A permission, such as `notes.delete-pinned`, or `signed-in`.
     *
     * @return bool Whether the actor is signed in, or a known token's caller, and holds it; `signed-in` asks only
     *              whether a user is signed in, which a token's caller never is.
     */
    public function can(string $permission): bool
    {
        if ($permission === self::SIGNED_IN) {
            return $this->signedIn();
        }

        return ($this->signedIn() || $this->client !== '') && in_array($permission, $this->permissions, true);
    }
}
