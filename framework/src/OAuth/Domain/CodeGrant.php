<?php

declare(strict_types=1);

namespace AIC\OAuth\Domain;

/**
 * What a redeemed authorization code or refresh token grants (#331, #352):
 * the person it acts for, the scopes they allowed, the path its tokens are
 * bound to (empty for none) and, for a refresh token, the family it
 * belongs to (empty for a code, which starts one).
 */
final readonly class CodeGrant
{
    /**
     * @param string       $userId   The person who allowed it.
     * @param list<string> $scopes   The scopes allowed.
     * @param string       $resource The path its tokens are bound to; empty for none.
     * @param string       $family   The refresh family; empty for a code.
     */
    public function __construct(
        public string $userId,
        public array $scopes,
        public string $resource = '',
        public string $family = '',
    ) {
    }
}
