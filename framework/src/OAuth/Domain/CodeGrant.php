<?php

declare(strict_types=1);

namespace AIC\OAuth\Domain;

/**
 * What a redeemed authorization code grants (#331): the person it acts for
 * and the scopes they allowed.
 */
final readonly class CodeGrant
{
    /**
     * @param string       $userId The person who allowed it.
     * @param list<string> $scopes The scopes allowed.
     */
    public function __construct(
        public string $userId,
        public array $scopes,
    ) {
    }
}
