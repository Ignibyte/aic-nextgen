<?php

declare(strict_types=1);

namespace AIC\Auth\Application;

use AIC\Auth\Domain\RouteAuthConfig;
use AIC\Http\Application\ResponseBuilder;
use LogicException;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function rawurlencode;

/**
 * Turns an anonymous visitor away from a protected path (#9): 303 to the
 * login page, with the path as `next` so the login can come back. It runs
 * after SessionMiddleware.
 */
final readonly class RequireAuthenticationMiddleware implements MiddlewareInterface
{
    /**
     * @param Authenticator   $authenticator   Knows who is signed in.
     * @param RouteAuthConfig $routeAuthConfig The protected paths and the login page.
     * @param ResponseBuilder $responseBuilder Makes the redirect.
     */
    public function __construct(
        private Authenticator $authenticator,
        private RouteAuthConfig $routeAuthConfig,
        private ResponseBuilder $responseBuilder,
    ) {
    }

    /**
     * @param ServerRequestInterface  $request The request, with its session.
     * @param RequestHandlerInterface $handler The rest of the stack.
     *
     * @throws LogicException When the request carries no session.
     *
     * @return ResponseInterface The handler's response, or 303 to the login page.
     */
    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();

        if (!$this->routeAuthConfig->protects($path) || $this->authenticator->userId($request) !== '') {
            return $handler->handle($request);
        }

        $location = $this->routeAuthConfig->loginPath . '?next=' . rawurlencode($path);

        $response = $this->responseBuilder->create(303, 'text/plain; charset=utf-8', '');

        return $this->responseBuilder->withHeader($response, 'Location', $location);
    }
}
