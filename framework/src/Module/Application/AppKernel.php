<?php

declare(strict_types=1);

namespace AIC\Module\Application;

use AIC\Container\Exception\ContainerException;
use AIC\Database\Contracts\Database;
use AIC\Http\Application\ResponseBuilder;
use AIC\Kernel\Application\Kernel;
use AIC\Routing\Application\RouteCollector;
use AIC\Routing\Application\Router;
use AIC\Routing\Domain\RoutePatternCompiler;
use AIC\Sapi\Contracts\RequestExtractor;
use LogicException;

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
     * @throws LogicException     When two modules declare one route, or a route pattern does not compile.
     * @throws ContainerException When a registered service cannot be resolved.
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
        $afterSession = (new ModuleLayers())->afterSession($webWiring, $handlerContext);
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
}
