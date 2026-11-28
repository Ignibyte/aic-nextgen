<?php

declare(strict_types=1);

namespace AIC\OAuth\Application;

use AIC\Database\Contracts\QueryException;
use AIC\OAuth\Contracts\OAuthRefusedException;
use AIC\OAuth\Domain\CodeGrant;
use AIC\OAuth\Domain\IssuedToken;
use AIC\OAuth\Domain\OAuthClient;
use AIC\OAuth\Domain\OAuthServer;
use AIC\OAuth\Domain\TokenAudience;
use AIC\OAuth\Domain\TokenStanding;

use function array_diff;
use function array_filter;
use function array_unique;
use function array_values;
use function explode;

/**
 * The grants the token endpoint issues (#331, #352). `client_credentials`
 * gives a confidential client a token of its own. `authorization_code`
 * redeems a code and starts a refresh family: the access token comes with a
 * refresh token. `refresh_token` spends one and answers the next access and
 * refresh tokens in the same family; its `scope` may narrow the access token
 * but the new refresh token keeps the family's (RFC 6749 section 6). A
 * `resource` (RFC 8707) binds the token to a path of this site, and must
 * match what the code or the family is already bound to.
 */
final readonly class TokenGrants
{
    /**
     * @param OAuthServer        $oauthServer        The server's settings.
     * @param AuthorizationCodes $authorizationCodes Redeems a code.
     * @param AccessTokens       $accessTokens       Issues access tokens.
     * @param RefreshTokens      $refreshTokens      Issues and spends refresh tokens.
     * @param TokenScopes        $tokenScopes        Reads the scopes a client asks for.
     * @param TokenAudience      $tokenAudience      Reads the path a resource names.
     */
    public function __construct(
        private OAuthServer $oauthServer,
        private AuthorizationCodes $authorizationCodes,
        private AccessTokens $accessTokens,
        private RefreshTokens $refreshTokens,
        private TokenScopes $tokenScopes = new TokenScopes(),
        private TokenAudience $tokenAudience = new TokenAudience(),
    ) {
    }

    /**
     * @param OAuthClient           $oauthClient The authenticated client.
     * @param array<string, string> $fields      The token request's fields.
     *
     * @throws OAuthRefusedException When the grant is missing, unknown or refused.
     * @throws QueryException        When the codes or tokens cannot be read or written.
     *
     * @return IssuedToken The token.
     */
    public function token(OAuthClient $oauthClient, array $fields): IssuedToken
    {
        $grantType = $fields['grant_type'] ?? '';
        $resource = $fields['resource'] ?? '';

        return match ($grantType) {
            'client_credentials' => $this->clientCredentials($oauthClient, $fields['scope'] ?? '', $resource),
            'authorization_code' => $this->exchanged($oauthClient, $fields, $resource),
            'refresh_token' => $this->refreshed($oauthClient, $fields, $resource),
            '' => throw new OAuthRefusedException('invalid_request', 'The request names no grant_type.'),
            default => throw new OAuthRefusedException(
                'unsupported_grant_type',
                'The grant ' . $grantType . ' is not one this server issues.',
            ),
        };
    }

    /**
     * @param OAuthClient $oauthClient The authenticated client.
     * @param string      $scope       The scopes it asks for; empty for all it holds.
     * @param string      $resource    The resource it asks for; empty for none.
     *
     * @throws OAuthRefusedException When the client is public, or the scope or resource is refused.
     * @throws QueryException        When the token cannot be written.
     *
     * @return IssuedToken The client's own token.
     */
    private function clientCredentials(OAuthClient $oauthClient, string $scope, string $resource): IssuedToken
    {
        if (!$oauthClient->confidential) {
            throw new OAuthRefusedException('unauthorized_client', 'A public client cannot use the client credentials grant.');
        }

        $scopes = $this->tokenScopes->asked($oauthClient, $scope);

        return $this->accessTokens->issue($oauthClient->id, '', $scopes, $this->oauthServer, '', $this->bound('', $resource));
    }

    /**
     * @param OAuthClient           $oauthClient The authenticated client.
     * @param array<string, string> $fields      The token request's fields.
     * @param string                $resource    The resource it asks for; empty for none.
     *
     * @throws OAuthRefusedException When the code or the resource is refused.
     * @throws QueryException        When the codes or tokens cannot be read or written.
     *
     * @return IssuedToken The access token with the first refresh token of a new family.
     */
    private function exchanged(OAuthClient $oauthClient, array $fields, string $resource): IssuedToken
    {
        $codeGrant = $this->authorizationCodes->redeem(
            $fields['code'] ?? '',
            $oauthClient->id,
            $fields['redirect_uri'] ?? '',
            $fields['code_verifier'] ?? '',
        );
        $family = new CodeGrant(
            $codeGrant->userId,
            $codeGrant->scopes,
            $this->bound($codeGrant->resource, $resource),
            $this->refreshTokens->family(),
        );

        return $this->renewable($oauthClient, $family, $family->scopes);
    }

    /**
     * @param OAuthClient           $oauthClient The authenticated client.
     * @param array<string, string> $fields      The token request's fields.
     * @param string                $resource    The resource it asks for; empty for none.
     *
     * @throws OAuthRefusedException When the refresh token is refused, or the scope or resource exceeds its family's.
     * @throws QueryException        When the tokens cannot be read or written.
     *
     * @return IssuedToken The next access and refresh tokens.
     */
    private function refreshed(OAuthClient $oauthClient, array $fields, string $resource): IssuedToken
    {
        $token = $fields['refresh_token'] ?? '';
        $scope = $fields['scope'] ?? '';
        $standing = $this->refreshTokens->standing($token);

        // A request refused for its scope or resource leaves the token unspent, so it is judged before the spend.
        if ($standing instanceof TokenStanding && $standing->clientId === $oauthClient->id) {
            self::narrowed($standing->scopes, $scope);
            $this->bound($standing->resource, $resource);
        }

        $spent = $this->refreshTokens->spend($token, $oauthClient->id);
        $codeGrant = new CodeGrant($spent->userId, $spent->scopes, $this->bound($spent->resource, $resource), $spent->family);

        return $this->renewable($oauthClient, $codeGrant, self::narrowed($spent->scopes, $scope));
    }

    /**
     * @param OAuthClient  $oauthClient The client.
     * @param CodeGrant    $codeGrant   What the family grants, its path and its id.
     * @param list<string> $scopes      What the access token holds.
     *
     * @throws QueryException When the tokens cannot be written.
     *
     * @return IssuedToken The access token with its refresh token.
     */
    private function renewable(OAuthClient $oauthClient, CodeGrant $codeGrant, array $scopes): IssuedToken
    {
        $issuedToken = $this->accessTokens->issue(
            $oauthClient->id,
            $codeGrant->userId,
            $scopes,
            $this->oauthServer,
            $codeGrant->family,
            $codeGrant->resource,
        );

        return new IssuedToken(
            $issuedToken->value,
            $issuedToken->seconds,
            $issuedToken->scopes,
            $this->refreshTokens->issue($oauthClient->id, $codeGrant, $this->oauthServer),
        );
    }

    /**
     * @param string $granted  The path the code or family is bound to; empty for none.
     * @param string $resource The resource the request asks for; empty for none.
     *
     * @throws OAuthRefusedException When the resource is not a path of this site, or not the one already bound
     *                               (`invalid_target`).
     *
     * @return string The path the token is bound to; empty for none.
     */
    private function bound(string $granted, string $resource): string
    {
        $path = $this->tokenAudience->path($resource, $this->oauthServer);

        if ($path === null || ($granted !== '' && $path !== '' && $path !== $granted)) {
            throw new OAuthRefusedException('invalid_target', 'The resource is not this site, or not the one already allowed.');
        }

        return $path === ''
            ? $granted
            : $path;
    }

    /**
     * @param list<string> $granted What the family grants.
     * @param string       $scope   The space-separated scopes asked for; empty for all of them.
     *
     * @throws OAuthRefusedException When one is beyond what the family grants (`invalid_scope`).
     *
     * @return list<string> The access token's scopes.
     */
    private static function narrowed(array $granted, string $scope): array
    {
        $asked = array_values(array_unique(array_filter(explode(' ', $scope), static fn (string $word): bool => $word !== '')));

        if ($asked === []) {
            return $granted;
        }

        if (array_diff($asked, $granted) !== []) {
            throw new OAuthRefusedException('invalid_scope', 'The scopes asked for are beyond what the refresh token grants.');
        }

        return $asked;
    }
}
