<?php

declare(strict_types=1);

namespace AIC\OAuth\Application;

use AIC\Access\Application\BearerTokens;
use AIC\Database\Contracts\Database;
use AIC\Http\Application\ResponseBuilder;
use AIC\OAuth\Domain\OAuthServer;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Puts the authorization server's paths in front of the app (#331, #352)
 * when the config names an issuer; with none the app is left as it is.
 */
final readonly class OAuthMount
{
    /**
     * @param RequestHandlerInterface $requestHandler  The app.
     * @param OAuthServer             $oauthServer     The server's settings.
     * @param Database                $database        The clients, codes and tokens.
     * @param ResponseBuilder         $responseBuilder Makes the answers.
     *
     * @return RequestHandlerInterface The server's paths, then the app; the app alone when the server is off.
     */
    public function around(
        RequestHandlerInterface $requestHandler,
        OAuthServer $oauthServer,
        Database $database,
        ResponseBuilder $responseBuilder,
    ): RequestHandlerInterface {
        if (!$oauthServer->enabled()) {
            return $requestHandler;
        }

        $oauthAnswers = new OAuthAnswers($responseBuilder);
        $oauthEndpoints = new OAuthEndpoints($oauthServer, $database, $oauthAnswers);

        return new OAuthPages(
            $requestHandler,
            $oauthServer,
            $oauthEndpoints->token(),
            $oauthEndpoints->revocation(),
            $oauthEndpoints->introspection(),
            new ClientRegistrar($oauthServer, new OAuthClients($database), new BearerTokens($database), $oauthAnswers),
            new OAuthMetadata($oauthServer, $responseBuilder),
        );
    }
}
