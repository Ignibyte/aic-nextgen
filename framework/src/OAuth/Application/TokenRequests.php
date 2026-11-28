<?php

declare(strict_types=1);

namespace AIC\OAuth\Application;

use AIC\Database\Contracts\QueryException;
use AIC\OAuth\Contracts\OAuthRefusedException;
use AIC\OAuth\Domain\TokenRequest;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Reads a revocation or introspection request (#352): its `token` (and the
 * `token_type_hint` the endpoints do not need, since both kinds are looked
 * up), with the client authenticated as at the token endpoint, by HTTP
 * Basic or `client_id` and `client_secret` in the body.
 */
final readonly class TokenRequests
{
    /**
     * The fields such a request is read from.
     */
    private const array FIELDS = ['token', 'token_type_hint', 'client_id', 'client_secret'];

    /**
     * @param ClientAuthentication $clientAuthentication Finds the client.
     * @param RequestFields        $requestFields        Reads the request's fields.
     */
    public function __construct(
        private ClientAuthentication $clientAuthentication,
        private RequestFields $requestFields = new RequestFields(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request.
     *
     * @throws OAuthRefusedException When the client does not authenticate (`invalid_client`).
     * @throws QueryException        When the clients cannot be read.
     *
     * @return TokenRequest The client and the token it names.
     */
    public function read(ServerRequestInterface $serverRequest): TokenRequest
    {
        $fields = $this->requestFields->read($serverRequest, self::FIELDS);

        return new TokenRequest($this->clientAuthentication->client($serverRequest, $fields), $fields['token'] ?? '');
    }
}
