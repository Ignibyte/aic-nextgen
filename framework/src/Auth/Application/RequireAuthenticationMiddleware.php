<?php

declare(strict_types=1);

namespace AIC\Auth\Application;

use AIC\Auth\Domain\RouteAuthConfig;
use AIC\Http\Application\ResponseBuilder;
use AIC\Http\Domain\BearerCredential;
use LogicException;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function rawurlencode;

/**
 * Turns an anonymous visitor away from a protected path (#9): 303 to the
 * login page, with the path (and its query, #331) as `next` so the login can
 * come back. It runs after SessionMiddleware. A bearer request (#313) is
 * not sent to sign in: its route's need judges its token, and a token never
 * meets `signed-in`.
 */
final readonly class RequireAuthenticationMiddleware implements MiddlewareInterface
{
    /**
     * @param Authenticator    $authenticator    Knows who is signed in.
     * @param RouteAuthConfig  $routeAuthConfig  The protected paths and the login page.
     * @param ResponseBuilder  $responseBuilder  Makes the redirect.
     * @param BearerCredential $bearerCredential Tells a bearer request.
     */
    public function __construct(
        private Authenticator $authenticator,
        private RouteAuthConfig $routeAuthConfig,
        private ResponseBuilder $responseBuilder,
        private BearerCredential $bearerCredential = new BearerCredential(),
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

        if (
            !$this->routeAuthConfig->protects($path)
            || $this->authenticator->userId($request) !== ''
            || $this->bearerCredential->present($request)
        ) {
            return $handler->handle($request);
        }

        $query = $request->getUri()->getQuery();
        $location = $this->routeAuthConfig->loginPath . '?next=' . rawurlencode($query === '' ? $path : $path . '?' . $query);

        $response = $this->responseBuilder->create(303, 'text/plain; charset=utf-8', '');

        return $this->responseBuilder->withHeader($response, 'Location', $location);
    }
}
