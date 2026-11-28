<?php

declare(strict_types=1);

namespace AIC\OAuth\Domain;

/**
 * A live token as introspection reports it (RFC 7662, #352): the client it
 * was issued to, the person it acts for (empty for a client's own), its
 * scopes, the path it is bound to (empty for none), when it expires (Unix
 * seconds) and whether it is a refresh token rather than an access token.
 */
final readonly class TokenStanding
{
    /**
     * @param string       $clientId The client it was issued to.
     * @param string       $userId   The person it acts for; empty for none.
     * @param list<string> $scopes   What it holds.
     * @param string       $resource The path it is bound to; empty for none.
     * @param int          $expires  When it expires, in Unix seconds.
     * @param bool         $refresh  Whether it is a refresh token.
     */
    public function __construct(
        public string $clientId,
        public string $userId,
        public array $scopes,
        public string $resource,
        public int $expires,
        public bool $refresh,
    ) {
    }
}
