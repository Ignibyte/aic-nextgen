<?php

declare(strict_types=1);

namespace AIC\OAuth\Application;

use AIC\OAuth\Contracts\OAuthRefusedException;
use AIC\OAuth\Domain\OAuthClient;

use function array_filter;
use function array_unique;
use function array_values;
use function explode;

/**
 * The scopes a request asks for (#331), space-separated as OAuth writes them:
 * none named (no field, or an empty one) means every scope the client may
 * hold, and each named one must be the client's.
 */
final readonly class TokenScopes
{
    /**
     * @param OAuthClient $oauthClient The client asking.
     * @param string      $scope       The `scope` field; empty when the request sent none.
     *
     * @throws OAuthRefusedException When a scope is not the client's (`invalid_scope`).
     *
     * @return list<string> The scopes, each once, in the order asked.
     */
    public function asked(OAuthClient $oauthClient, string $scope): array
    {
        if ($scope === '') {
            return $oauthClient->scopes;
        }

        $scopes = array_values(array_unique(array_filter(explode(' ', $scope), static fn (string $word): bool => $word !== '')));

        if ($scopes === [] || !$oauthClient->holds($scopes)) {
            throw new OAuthRefusedException('invalid_scope', 'The scopes asked for are not ones this client may hold.');
        }

        return $scopes;
    }
}
