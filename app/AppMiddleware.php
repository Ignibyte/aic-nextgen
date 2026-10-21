<?php

declare(strict_types=1);

namespace App;

use AIC\Database\Contracts\Database;
use AIC\Form\Application\FormGuardMiddleware;
use AIC\Http\Application\ResponseBuilder;
use AIC\Middleware\Application\MiddlewareStep;
use AIC\Routing\Application\RouteErrorMiddleware;
use AIC\Session\Application\PostgresSessionStore;
use AIC\Session\Application\RequestSession;
use AIC\Session\Application\SessionMiddleware;
use AIC\Session\Domain\RandomTokens;
use AIC\Session\Domain\StringMapJson;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * The app's middleware stack around its router, outermost first: the
 * routing errors (404, 405), the session, the sign-in check on protected
 * paths (#9), then the form guard (CSRF and the honeypot on every unsafe
 * request).
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
            new RandomTokens(),
            new StringMapJson(),
            $this->responseBuilder,
            self::SESSION_TTL,
        );

        $tesseraStack = new TesseraStack($this->responseBuilder, $this->database);
        $guardStep = new MiddlewareStep(
            new FormGuardMiddleware(new RequestSession(), $this->responseBuilder),
            $tesseraStack->writes($requestHandler),
        );
        $authStep = new MiddlewareStep((new AppAccess($this->database, $this->responseBuilder))->middleware(), $guardStep);
        $middlewareStep = new MiddlewareStep($sessionMiddleware, $authStep);

        return $tesseraStack->edge(new MiddlewareStep(new RouteErrorMiddleware($this->responseBuilder), $middlewareStep));
    }
}
