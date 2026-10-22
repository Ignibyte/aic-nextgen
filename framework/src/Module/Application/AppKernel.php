<?php

declare(strict_types=1);

namespace AIC\Module\Application;

use AIC\Database\Contracts\Database;
use AIC\Http\Application\ResponseBuilder;
use AIC\Kernel\Application\Kernel;
use AIC\Routing\Application\RouteCollector;
use AIC\Routing\Application\Router;
use AIC\Routing\Domain\RoutePatternCompiler;
use AIC\Sapi\Contracts\RequestExtractor;
use LogicException;
use Psr\Http\Server\MiddlewareInterface;

/**
 * The app's web side, composed from the generated wiring (#93): the handler
 * context with the modules' plugins, their handlers behind the router, the
 * framework's stack around it with the modules' middleware after the session,
 * and the kernel. The wiring brings plugins, handlers and middleware only when
 * some module has them (`PluginWiring`, `HandlerWiring`, `MiddlewareWiring`).
 * `bootstrap/serve.php` builds it once per request.
 */
final readonly class AppKernel
{
    /**
     * The composed kernel.
     */
    private Kernel $kernel;

    /**
     * @param HandlerContexts  $handlerContexts  Builds the handler context from the app's roots.
     * @param RequestExtractor $requestExtractor Turns the SAPI request into PSR-7.
     * @param ResponseBuilder  $responseBuilder  Makes the PSR-7 responses.
     * @param Database         $database         The database.
     * @param WebWiring        $webWiring        The modules' generated wiring.
     *
     * @throws LogicException When two modules declare one route, or a route pattern does not compile.
     */
    public function __construct(
        HandlerContexts $handlerContexts,
        RequestExtractor $requestExtractor,
        ResponseBuilder $responseBuilder,
        Database $database,
        WebWiring $webWiring,
    ) {
        $handlerContext = $handlerContexts->create($webWiring);
        $routeTable = $webWiring->routes(new RouteCollector(new RoutePatternCompiler()))->table();
        $handlers = $webWiring instanceof HandlerWiring
            ? $webWiring->handlers($handlerContext)
            : [];
        $afterSession = $this->afterSession($webWiring, $handlerContext);
        $this->kernel = new Kernel(
            $requestExtractor,
            (new FrameworkStack($responseBuilder, $database))->around(new Router($routeTable, $handlers), $afterSession),
            $responseBuilder,
        );
    }

    /**
     * @return Kernel The kernel that serves the request.
     */
    public function kernel(): Kernel
    {
        return $this->kernel;
    }

    /**
     * An if per interface, not `match (true)`: elephc narrows `instanceof` in an
     * if, not in a match arm (#112).
     *
     * @param WebWiring      $webWiring      The modules' wiring.
     * @param HandlerContext $handlerContext The roots.
     *
     * @return list<MiddlewareInterface> The modules' middleware after the session; none when no module has any.
     */
    private function afterSession(WebWiring $webWiring, HandlerContext $handlerContext): array
    {
        if ($webWiring instanceof MiddlewareWiring) {
            return $webWiring->afterSession($handlerContext);
        }

        if ($webWiring instanceof RootlessMiddlewareWiring) {
            return $webWiring->afterSession();
        }

        return [];
    }
}
