<?php

declare(strict_types=1);

namespace App\Account\Service;

use JsonSerializable;
use Override;

/**
 * `/api/caller`'s answer (#313): the token's name or the user's id, and what
 * the caller holds.
 */
final readonly class CallerBody implements JsonSerializable
{
    /**
     * @param string       $client      The bearer token's name; empty for a signed-in user.
     * @param string       $user        The signed-in user's id; empty for a token's caller.
     * @param list<string> $permissions The token's scopes, or the user's permissions.
     */
    public function __construct(
        private string $client,
        private string $user,
        private array $permissions,
    ) {
    }

    /**
     * @return array{client: string, user: string, permissions: list<string>} The body.
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return ['client' => $this->client, 'user' => $this->user, 'permissions' => $this->permissions];
    }
}
