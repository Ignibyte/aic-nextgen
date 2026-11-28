<?php

declare(strict_types=1);

namespace AIC\OAuth\Domain;

/**
 * An access token as the token endpoint hands it out (#331): the value, which
 * only its holder keeps, how many seconds it lives, the scopes it holds and,
 * for a code-flow or refreshed token, the refresh token that renews it
 * (#352; empty for client credentials).
 */
final readonly class IssuedToken
{
    /**
     * @param string       $value   The token.
     * @param int          $seconds How long it lives.
     * @param list<string> $scopes  What it holds.
     * @param string       $refresh The refresh token issued with it; empty for none.
     */
    public function __construct(
        public string $value,
        public int $seconds,
        public array $scopes,
        public string $refresh = '',
    ) {
    }
}
