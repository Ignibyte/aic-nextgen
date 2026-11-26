<?php

declare(strict_types=1);

namespace AIC\OAuth\Application;

use AIC\Database\Contracts\QueryException;
use AIC\Http\Application\ResponseBuilder;
use AIC\OAuth\Contracts\OAuthRefusedException;
use AIC\OAuth\Domain\IssuedToken;
use AIC\OAuth\Domain\OAuthClient;
use AIC\OAuth\Domain\OAuthServer;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use function implode;
use function is_string;
use function json_encode;

use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * `POST /oauth/token` (#331): the client credentials grant, for a confidential
 * client's own calls, and the authorization code grant with PKCE, for a client
 * acting for the person who allowed it. Either answers an access token as
 * JSON (RFC 6749 section 5.1), never cached; a refusal answers its error
 * (section 5.2), `invalid_client` as 401 with a Basic challenge.
 */
final readonly class TokenEndpoint
{
    /**
     * The fields a token request is read from.
     */
    private const array FIELDS = ['grant_type', 'client_id', 'client_secret', 'scope', 'code', 'redirect_uri', 'code_verifier'];

    /**
     * @param OAuthServer          $oauthServer          The server's settings.
     * @param ClientAuthentication $clientAuthentication Finds the client.
     * @param AuthorizationCodes   $authorizationCodes   Redeems a code.
     * @param AccessTokens         $accessTokens         Issues the token.
     * @param TokenScopes          $tokenScopes          Reads the scopes a client asks for.
     * @param ResponseBuilder      $responseBuilder      Makes the answer.
     * @param RequestFields        $requestFields        Reads the request's fields.
     */
    public function __construct(
        private OAuthServer $oauthServer,
        private ClientAuthentication $clientAuthentication,
        private AuthorizationCodes $authorizationCodes,
        private AccessTokens $accessTokens,
        private TokenScopes $tokenScopes,
        private ResponseBuilder $responseBuilder,
        private RequestFields $requestFields = new RequestFields(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest A request to the token endpoint.
     *
     * @throws QueryException           When the clients, codes or tokens cannot be read or written.
     * @throws InvalidArgumentException When a response cannot be made.
     *
     * @return ResponseInterface The token, or the refusal.
     */
    public function answer(ServerRequestInterface $serverRequest): ResponseInterface
    {
        if ($serverRequest->getMethod() !== 'POST') {
            $response = $this->responseBuilder->create(405, 'text/plain; charset=utf-8', "The token endpoint takes POST.\n");

            return $this->responseBuilder->withHeader($response, 'Allow', 'POST');
        }

        try {
            $fields = $this->requestFields->read($serverRequest, self::FIELDS);
            $client = $this->clientAuthentication->client($serverRequest, $fields);

            return $this->issued($this->token($client, $fields));
        } catch (OAuthRefusedException $oAuthRefusedException) {
            return $this->refused($oAuthRefusedException);
        }
    }

    /**
     * @param OAuthClient           $oauthClient The client.
     * @param array<string, string> $fields      The request's fields.
     *
     * @throws QueryException        When a code or the token cannot be read or written.
     * @throws OAuthRefusedException When the grant is missing, unknown, not the client's to use, or refused.
     *
     * @return IssuedToken The token.
     */
    private function token(OAuthClient $oauthClient, array $fields): IssuedToken
    {
        $grantType = $fields['grant_type'] ?? '';

        if ($grantType === 'client_credentials') {
            if (!$oauthClient->confidential) {
                throw new OAuthRefusedException('unauthorized_client', 'A public client cannot use the client credentials grant.');
            }

            $scopes = $this->tokenScopes->asked($oauthClient, $fields['scope'] ?? '');

            return $this->accessTokens->issue($oauthClient->id, '', $scopes, $this->oauthServer);
        }

        if ($grantType === 'authorization_code') {
            $codeGrant = $this->authorizationCodes->redeem(
                $fields['code'] ?? '',
                $oauthClient->id,
                $fields['redirect_uri'] ?? '',
                $fields['code_verifier'] ?? '',
            );

            return $this->accessTokens->issue($oauthClient->id, $codeGrant->userId, $codeGrant->scopes, $this->oauthServer);
        }

        throw $grantType === ''
            ? new OAuthRefusedException('invalid_request', 'The request names no grant_type.')
            : new OAuthRefusedException('unsupported_grant_type', 'The grant ' . $grantType . ' is not one this server issues.');
    }

    /**
     * @param IssuedToken $issuedToken The token.
     *
     * @throws InvalidArgumentException When the response cannot be made.
     *
     * @return ResponseInterface 200 with the token.
     */
    private function issued(IssuedToken $issuedToken): ResponseInterface
    {
        return $this->uncached($this->responseBuilder->create(
            200,
            'application/json',
            '{"access_token":' . self::json($issuedToken->value) . ',"token_type":"Bearer","expires_in":'
                . $issuedToken->seconds . ',"scope":' . self::json(implode(' ', $issuedToken->scopes)) . '}',
        ));
    }

    /**
     * @param OAuthRefusedException $oauthRefusedException The refusal.
     *
     * @throws InvalidArgumentException When the response cannot be made.
     *
     * @return ResponseInterface Its status with the error.
     */
    private function refused(OAuthRefusedException $oauthRefusedException): ResponseInterface
    {
        $response = $this->uncached($this->responseBuilder->create(
            $oauthRefusedException->status,
            'application/json',
            '{"error":' . self::json($oauthRefusedException->error) . ',"error_description":'
                . self::json($oauthRefusedException->description) . '}',
        ));

        return $oauthRefusedException->status === 401
            ? $this->responseBuilder->withHeader($response, 'WWW-Authenticate', 'Basic realm="oauth"')
            : $response;
    }

    /**
     * @param ResponseInterface $response An answer.
     *
     * @throws InvalidArgumentException When a header cannot be set.
     *
     * @return ResponseInterface It, never to be cached (RFC 6749 section 5.1).
     */
    private function uncached(ResponseInterface $response): ResponseInterface
    {
        return $this->responseBuilder->withHeader(
            $this->responseBuilder->withHeader($response, 'Cache-Control', 'no-store'),
            'Pragma',
            'no-cache',
        );
    }

    /**
     * @param string $text Text.
     *
     * @return string It as a JSON string.
     */
    private static function json(string $text): string
    {
        $json = json_encode($text, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return is_string($json)
            ? $json
            : '""';
    }
}
