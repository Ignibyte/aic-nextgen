<?php

declare(strict_types=1);

namespace App;

use AIC\Database\Contracts\Database;
use AIC\Http\Application\ResponseBuilder;
use AIC\Middleware\Application\MiddlewareStep;
use AIC\Routing\Application\RouteErrorMiddleware;
use AIC\Session\Application\PostgresSessionStore;
use AIC\Session\Application\SessionMiddleware;
use AIC\Session\Domain\SessionCodec;
use AIC\Session\Domain\SessionIds;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * The app's middleware stack around its router, outermost first: the
 * routing errors (404, 405), then the session.
 */
final readonly class AppMiddleware
{
    /**
     * Seconds a session lives after its last request.
     */
    private const int SESSION_TTL = 7_200;

    /**
     * @param ResponseBuilder $responseBuilder Makes the error responses.
     * @param Database        $database        Holds the sessions.
     */
    public function __construct(
        private ResponseBuilder $responseBuilder,
        private Database $database,
    ) {
    }

    /**
     * @param RequestHandlerInterface $requestHandler The router, or any handler.
     *
     * @return RequestHandlerInterface It inside the stack.
     */
    public function around(RequestHandlerInterface $requestHandler): RequestHandlerInterface
    {
        $sessionMiddleware = new SessionMiddleware(
            new PostgresSessionStore($this->database),
            new SessionIds(),
            new SessionCodec(),
            $this->responseBuilder,
            self::SESSION_TTL,
        );

        $middlewareStep = new MiddlewareStep($sessionMiddleware, $requestHandler);

        return new MiddlewareStep(new RouteErrorMiddleware($this->responseBuilder), $middlewareStep);
    }
}
