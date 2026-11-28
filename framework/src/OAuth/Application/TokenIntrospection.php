<?php

declare(strict_types=1);

namespace AIC\OAuth\Application;

use AIC\Database\Contracts\QueryException;
use AIC\OAuth\Contracts\OAuthRefusedException;
use AIC\OAuth\Domain\OAuthServer;
use AIC\OAuth\Domain\TokenStanding;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use function implode;
use function in_array;

/**
 * `POST /oauth/introspect` (RFC 7662, #352): a resource server that is not
 * this app asks whether a token is live. The caller authenticates as a
 * confidential client holding `oauth.introspect` (the operator's `client:add
 * <id> <secret> oauth.introspect`); another client is 403
 * `unauthorized_client`. A live access or unspent refresh token answers
 * `active` with its scope, client, expiry, issuer, the person it acts for
 * (`sub`) and its audience (`aud`) when it has them; anything else answers
 * `{"active":false}`.
 */
final readonly class TokenIntrospection
{
    /**
     * The permission a client needs to introspect.
     */
    public const string PERMISSION = 'oauth.introspect';

    /**
     * @param OAuthServer   $oauthServer   The server, for the issuer.
     * @param TokenRequests $tokenRequests Reads the request and authenticates its client.
     * @param AccessTokens  $accessTokens  Looks up an access token.
     * @param RefreshTokens $refreshTokens Looks up a refresh token.
     * @param OAuthAnswers  $oauthAnswers  Makes the answer.
     */
    public function __construct(
        private OAuthServer $oauthServer,
        private TokenRequests $tokenRequests,
        private AccessTokens $accessTokens,
        private RefreshTokens $refreshTokens,
        private OAuthAnswers $oauthAnswers,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest A request to the introspection endpoint.
     *
     * @throws QueryException           When the clients or tokens cannot be read.
     * @throws InvalidArgumentException When a response cannot be made.
     *
     * @return ResponseInterface The token's standing, or the refusal.
     */
    public function answer(ServerRequestInterface $serverRequest): ResponseInterface
    {
        if ($serverRequest->getMethod() !== 'POST') {
            return $this->oauthAnswers->postOnly('introspection endpoint');
        }

        try {
            $tokenRequest = $this->tokenRequests->read($serverRequest);
            $client = $tokenRequest->client;

            if (!$client->confidential || !in_array(self::PERMISSION, $client->scopes, true)) {
                throw new OAuthRefusedException('unauthorized_client', 'This client may not introspect tokens.', 403);
            }

            $token = $tokenRequest->token;
            $standing = $token === ''
                ? null
                : $this->accessTokens->standing($token) ?? $this->refreshTokens->standing($token);

            return $this->oauthAnswers->document(200, $this->described($standing));
        } catch (OAuthRefusedException $oAuthRefusedException) {
            return $this->oauthAnswers->refused($oAuthRefusedException);
        }
    }

    /**
     * @param TokenStanding|null $tokenStanding The token while it is live; null otherwise.
     *
     * @return string RFC 7662's answer.
     */
    private function described(?TokenStanding $tokenStanding): string
    {
        if (!$tokenStanding instanceof TokenStanding) {
            return '{"active":false}';
        }

        $members = [
            '"active":true',
            '"scope":' . $this->oauthAnswers->text(implode(' ', $tokenStanding->scopes)),
            '"client_id":' . $this->oauthAnswers->text($tokenStanding->clientId),
            '"exp":' . $tokenStanding->expires,
            '"iss":' . $this->oauthAnswers->text($this->oauthServer->url('')),
        ];

        if (!$tokenStanding->refresh) {
            $members[] = '"token_type":"Bearer"';
        }

        if ($tokenStanding->userId !== '') {
            $members[] = '"sub":' . $this->oauthAnswers->text($tokenStanding->userId);
        }

        if ($tokenStanding->resource !== '') {
            $members[] = '"aud":' . $this->oauthAnswers->text($this->oauthServer->url($tokenStanding->resource));
        }

        return '{' . implode(',', $members) . '}';
    }
}
