<?php

declare(strict_types=1);

namespace AIC\Module\Application;

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
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function array_reverse;

/**
 * The framework's middleware stack around the router (#93, from the demo's
 * `AppMiddleware`), outermost first: Tessera's protocol check and redirect
 * rewrite, route errors, the Postgres session, the modules' middleware that
 * runs after the session, the form guard, Tessera's write ledger. The order is
 * behaviour: the guard's 403 and 419 and the sign-in redirect depend on it.
 */
final readonly class FrameworkStack
{
    /**
     * How long a session lives without a request, in seconds.
     */
    private const int SESSION_TTL = 7_200;

    /**
     * @param ResponseBuilder $responseBuilder Makes the error responses.
     * @param Database        $database        Holds the sessions and the write ledger.
     */
    public function __construct(
        private ResponseBuilder $responseBuilder,
        private Database $database,
    ) {
    }

    /**
     * @param RequestHandlerInterface   $requestHandler The router.
     * @param list<MiddlewareInterface> $afterSession   The modules' middleware after the session, in order.
     *
     * @return RequestHandlerInterface The router inside the stack.
     */
    public function around(RequestHandlerInterface $requestHandler, array $afterSession): RequestHandlerInterface
    {
        $tesseraLayers = new TesseraLayers($this->responseBuilder, $this->database);
        $formGuardMiddleware = new FormGuardMiddleware(new RequestSession(), $this->responseBuilder);
        $inner = new MiddlewareStep($formGuardMiddleware, $tesseraLayers->writes($requestHandler));

        foreach (array_reverse($afterSession) as $middleware) {
            $inner = new MiddlewareStep($middleware, $inner);
        }

        $sessionMiddleware = new SessionMiddleware(
            new PostgresSessionStore($this->database),
            new RandomTokens(),
            new StringMapJson(),
            $this->responseBuilder,
            self::SESSION_TTL,
        );

        $sessionStep = new MiddlewareStep($sessionMiddleware, $inner);

        return $tesseraLayers->edge(new MiddlewareStep(new RouteErrorMiddleware($this->responseBuilder), $sessionStep));
    }
}
