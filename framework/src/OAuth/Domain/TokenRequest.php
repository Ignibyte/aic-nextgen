<?php

declare(strict_types=1);

namespace AIC\OAuth\Domain;

/**
 * A revocation or introspection request (RFC 7009, RFC 7662, #352) once its
 * client has authenticated: the client and the token it names (empty for
 * none).
 */
final readonly class TokenRequest
{
    /**
     * @param OAuthClient $client The authenticated client.
     * @param string      $token  The token it names; empty for none.
     */
    public function __construct(
        public OAuthClient $client,
        public string $token,
    ) {
    }
}
