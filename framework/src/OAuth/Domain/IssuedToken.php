<?php

declare(strict_types=1);

namespace AIC\OAuth\Domain;

/**
 * An access token as the token endpoint hands it out (#331): the value, which
 * only its holder keeps, how many seconds it lives and the scopes it holds.
 */
final readonly class IssuedToken
{
    /**
     * @param string       $value   The token.
     * @param int          $seconds How long it lives.
     * @param list<string> $scopes  What it holds.
     */
    public function __construct(
        public string $value,
        public int $seconds,
        public array $scopes,
    ) {
    }
}
