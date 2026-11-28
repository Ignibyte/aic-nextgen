<?php

declare(strict_types=1);

namespace AIC\OAuth\Application;

use AIC\Database\Contracts\Database;
use AIC\OAuth\Domain\OAuthServer;

/**
 * Wires the endpoints a client authenticates to (#352), for `OAuthMount`:
 * the token endpoint with its grants, revocation and introspection, each
 * over the same clients, codes and tokens in Postgres.
 */
final readonly class OAuthEndpoints
{
    /**
     * @param OAuthServer  $oauthServer  The server's settings.
     * @param Database     $database     The clients, codes and tokens.
     * @param OAuthAnswers $oauthAnswers Makes the answers.
     */
    public function __construct(
        private OAuthServer $oauthServer,
        private Database $database,
        private OAuthAnswers $oauthAnswers,
    ) {
    }

    /**
     * @return TokenEndpoint `POST /oauth/token`.
     */
    public function token(): TokenEndpoint
    {
        $accessTokens = new AccessTokens($this->database);
        $tokenGrants = new TokenGrants(
            $this->oauthServer,
            new AuthorizationCodes($this->database),
            $accessTokens,
            new RefreshTokens($this->database, $accessTokens),
        );

        return new TokenEndpoint($this->clientAuthentication(), $tokenGrants, $this->oauthAnswers);
    }

    /**
     * @return TokenRevocation `POST /oauth/revoke`.
     */
    public function revocation(): TokenRevocation
    {
        $accessTokens = new AccessTokens($this->database);

        return new TokenRevocation(
            new TokenRequests($this->clientAuthentication()),
            $accessTokens,
            new RefreshTokens($this->database, $accessTokens),
            $this->oauthAnswers,
        );
    }

    /**
     * @return TokenIntrospection `POST /oauth/introspect`.
     */
    public function introspection(): TokenIntrospection
    {
        $accessTokens = new AccessTokens($this->database);

        return new TokenIntrospection(
            $this->oauthServer,
            new TokenRequests($this->clientAuthentication()),
            $accessTokens,
            new RefreshTokens($this->database, $accessTokens),
            $this->oauthAnswers,
        );
    }

    /**
     * @return ClientAuthentication Finds a client by its own authentication.
     */
    private function clientAuthentication(): ClientAuthentication
    {
        return new ClientAuthentication(new OAuthClients($this->database));
    }
}
