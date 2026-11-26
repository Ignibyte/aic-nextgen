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
 * The authorization server's own paths (#331), in front of the app and its
 * session like the profiler's pages: the token endpoint, which judges each
 * request by the client's own authentication and so needs no CSRF token, and
 * the two metadata documents. Every other request goes on to the app.
 */
final readonly class OAuthPages implements RequestHandlerInterface
{
    /**
     * @param RequestHandlerInterface $requestHandler The app, which answers everything else.
     * @param TokenEndpoint           $tokenEndpoint  Answers `/oauth/token`.
     * @param OAuthMetadata           $oauthMetadata  Answers the metadata.
     */
    public function __construct(
        private RequestHandlerInterface $requestHandler,
        private TokenEndpoint $tokenEndpoint,
        private OAuthMetadata $oauthMetadata,
    ) {
    }

    /**
     * @param ServerRequestInterface $request The request.
     *
     * @throws QueryException           When the token endpoint cannot read or write its tables.
     * @throws InvalidArgumentException When a response cannot be made.
     *
     * @return ResponseInterface The server's answer for its paths, the app's for the rest.
     */
    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $path = $request->getUri()->getPath();

        if ($path === OAuthServer::TOKEN_PATH) {
            return $this->tokenEndpoint->answer($request);
        }

        if ($request->getMethod() !== 'GET') {
            return $this->requestHandler->handle($request);
        }

        if ($path === OAuthServer::SERVER_METADATA_PATH) {
            return $this->oauthMetadata->server();
        }

        return $path === OAuthServer::RESOURCE_METADATA_PATH || str_starts_with($path, OAuthServer::RESOURCE_METADATA_PATH . '/')
            ? $this->oauthMetadata->resource($path)
            : $this->requestHandler->handle($request);
    }
}
