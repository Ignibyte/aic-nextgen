<?php

declare(strict_types=1);

namespace AIC\OAuth\Application;

use AIC\Database\Contracts\QueryException;
use AIC\OAuth\Contracts\OAuthRefusedException;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * `POST /oauth/revoke` (RFC 7009, #352): a client gives back a token it no
 * longer needs. The client authenticates as at the token endpoint; an access
 * token of its own goes, and a refresh token of its own takes its whole
 * family with it. Any other token, unknown, expired or another client's, is
 * answered the same 200 with no body (section 2.2), so nothing about it
 * leaks; `token_type_hint` is not needed, since both kinds are looked up.
 */
final readonly class TokenRevocation
{
    /**
     * @param TokenRequests $tokenRequests Reads the request and authenticates its client.
     * @param AccessTokens  $accessTokens  Removes an access token.
     * @param RefreshTokens $refreshTokens Removes a refresh token's family.
     * @param OAuthAnswers  $oauthAnswers  Makes the answer.
     */
    public function __construct(
        private TokenRequests $tokenRequests,
        private AccessTokens $accessTokens,
        private RefreshTokens $refreshTokens,
        private OAuthAnswers $oauthAnswers,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest A request to the revocation endpoint.
     *
     * @throws QueryException           When the clients or tokens cannot be read or written.
     * @throws InvalidArgumentException When a response cannot be made.
     *
     * @return ResponseInterface An empty 200, or the refusal.
     */
    public function answer(ServerRequestInterface $serverRequest): ResponseInterface
    {
        if ($serverRequest->getMethod() !== 'POST') {
            return $this->oauthAnswers->postOnly('revocation endpoint');
        }

        try {
            $tokenRequest = $this->tokenRequests->read($serverRequest);

            if ($tokenRequest->token === '') {
                throw new OAuthRefusedException('invalid_request', 'The request names no token.');
            }

            if (!$this->refreshTokens->revoke($tokenRequest->token, $tokenRequest->client->id)) {
                $this->accessTokens->revoke($tokenRequest->token, $tokenRequest->client->id);
            }

            return $this->oauthAnswers->done();
        } catch (OAuthRefusedException $oAuthRefusedException) {
            return $this->oauthAnswers->refused($oAuthRefusedException);
        }
    }
}
