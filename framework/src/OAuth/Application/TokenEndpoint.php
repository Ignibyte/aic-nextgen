<?php

declare(strict_types=1);

namespace AIC\OAuth\Application;

use AIC\Database\Contracts\QueryException;
use AIC\OAuth\Contracts\OAuthRefusedException;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * `POST /oauth/token` (#331, #352): the client authenticates and
 * `TokenGrants` issues the token its grant asks for, answered as JSON
 * (RFC 6749 section 5.1), never cached; a refusal answers its error
 * (section 5.2), `invalid_client` as 401 with a Basic challenge.
 */
final readonly class TokenEndpoint
{
    /**
     * The fields a token request is read from.
     */
    private const array FIELDS = [
        'grant_type', 'client_id', 'client_secret', 'scope', 'code', 'redirect_uri', 'code_verifier', 'refresh_token',
        'resource',
    ];

    /**
     * @param ClientAuthentication $clientAuthentication Finds the client.
     * @param TokenGrants          $tokenGrants          Issues the token.
     * @param OAuthAnswers         $oauthAnswers         Makes the answer.
     * @param RequestFields        $requestFields        Reads the request's fields.
     */
    public function __construct(
        private ClientAuthentication $clientAuthentication,
        private TokenGrants $tokenGrants,
        private OAuthAnswers $oauthAnswers,
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
            return $this->oauthAnswers->postOnly('token endpoint');
        }

        try {
            $fields = $this->requestFields->read($serverRequest, self::FIELDS);
            $client = $this->clientAuthentication->client($serverRequest, $fields);

            return $this->oauthAnswers->issued($this->tokenGrants->token($client, $fields));
        } catch (OAuthRefusedException $oAuthRefusedException) {
            return $this->oauthAnswers->refused($oAuthRefusedException);
        }
    }
}
