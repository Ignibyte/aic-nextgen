<?php

declare(strict_types=1);

namespace AIC\OAuth\Application;

use AIC\Database\Contracts\QueryException;
use AIC\OAuth\Domain\OAuthServer;
use InvalidArgumentException;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function str_starts_with;

/**
 * The authorization server's own paths (#331, #352), in front of the app and
 * its session like the profiler's pages: the token, revocation and
 * introspection endpoints, which judge each request by the client's own
 * authentication and so need no CSRF token, the registration endpoint while
 * registration is on, and the two metadata documents. Every other request
 * goes on to the app.
 */
final readonly class OAuthPages implements RequestHandlerInterface
{
    /**
     * @param RequestHandlerInterface $requestHandler     The app, which answers everything else.
     * @param OAuthServer             $oauthServer        The server's settings.
     * @param TokenEndpoint           $tokenEndpoint      Answers `/oauth/token`.
     * @param TokenRevocation         $tokenRevocation    Answers `/oauth/revoke`.
     * @param TokenIntrospection      $tokenIntrospection Answers `/oauth/introspect`.
     * @param ClientRegistrar         $clientRegistrar    Answers `/oauth/register`.
     * @param OAuthMetadata           $oauthMetadata      Answers the metadata.
     */
    public function __construct(
        private RequestHandlerInterface $requestHandler,
        private OAuthServer $oauthServer,
        private TokenEndpoint $tokenEndpoint,
        private TokenRevocation $tokenRevocation,
        private TokenIntrospection $tokenIntrospection,
        private ClientRegistrar $clientRegistrar,
        private OAuthMetadata $oauthMetadata,
    ) {
    }

    /**
     * @param ServerRequestInterface $request The request.
     *
     * @throws QueryException           When an endpoint cannot read or write its tables.
     * @throws InvalidArgumentException When a response cannot be made.
     *
     * @return ResponseInterface The endpoint's or metadata's answer, or the app's.
     */
    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $endpoint = $this->endpoint($request);

        if ($endpoint instanceof ResponseInterface) {
            return $endpoint;
        }

        if ($request->getMethod() !== 'GET') {
            return $this->requestHandler->handle($request);
        }

        $path = $request->getUri()->getPath();

        if ($path === OAuthServer::SERVER_METADATA_PATH) {
            return $this->oauthMetadata->server();
        }

        return $path === OAuthServer::RESOURCE_METADATA_PATH || str_starts_with($path, OAuthServer::RESOURCE_METADATA_PATH . '/')
            ? $this->oauthMetadata->resource($path)
            : $this->requestHandler->handle($request);
    }

    /**
     * @param ServerRequestInterface $serverRequest The request.
     *
     * @throws QueryException           When an endpoint cannot read or write its tables.
     * @throws InvalidArgumentException When a response cannot be made.
     *
     * @return ResponseInterface|null The endpoint's answer; null when the path is not an endpoint's.
     */
    private function endpoint(ServerRequestInterface $serverRequest): ?ResponseInterface
    {
        $path = $serverRequest->getUri()->getPath();

        if ($path === OAuthServer::TOKEN_PATH) {
            return $this->tokenEndpoint->answer($serverRequest);
        }

        if ($path === OAuthServer::REVOKE_PATH) {
            return $this->tokenRevocation->answer($serverRequest);
        }

        if ($path === OAuthServer::INTROSPECT_PATH) {
            return $this->tokenIntrospection->answer($serverRequest);
        }

        return $path === OAuthServer::REGISTER_PATH && $this->oauthServer->registers()
            ? $this->clientRegistrar->answer($serverRequest)
            : null;
    }
}
