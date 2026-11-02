<?php

declare(strict_types=1);

namespace AIC\Module\Application;

use AIC\Container\Exception\ContainerException;
use AIC\Kernel\Contracts\RequestObserver;
use AIC\Routing\Application\RouteCollector;
use AIC\Routing\Application\Router;
use AIC\Routing\Domain\RoutePatternCompiler;
use LogicException;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/** Store roots without application work; compose the existing dynamic stack only when invoked. */
final readonly class DynamicApplication implements RequestHandlerInterface
{
    /**
     * @param HandlerContexts $handlerContexts Builds the dynamic constructor roots and plugins.
     * @param WebWiring       $webWiring       The compiled modules' wiring.
     * @param FrameworkStack  $frameworkStack  Stores the response/database roots for the existing middleware.
     * @param WebPresentation $webPresentation The already composed build-owned presentation catalogs.
     */
    public function __construct(
        private HandlerContexts $handlerContexts,
        private WebWiring $webWiring,
        private FrameworkStack $frameworkStack,
        private WebPresentation $webPresentation,
    ) {
    }

    /**
     * @param ServerRequestInterface $request A request outside the public asset namespace.
     *
     * @throws ContainerException When a module provider cannot be resolved.
     * @throws LogicException     When declared routes conflict or a pattern is invalid.
     *
     * @return ResponseInterface Its response through the existing framework/session stack.
     */
    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $handlerContext = $this->handlerContexts->create($this->webWiring, $this->webPresentation);
        $routeTable = $this->webWiring->routes(new RouteCollector(new RoutePatternCompiler()))->table();
        $handlers = $this->webWiring instanceof HandlerWiring
            ? $this->webWiring->handlers($handlerContext)
            : [];
        $afterSession = (new ModuleLayers())->afterSession($this->webWiring, $handlerContext);

        $requestObservers = $this->handlerContexts->observers();
        $router = new Router($routeTable, $handlers, $requestObservers->route);
        $requestObserver = $requestObservers->request;

        if (!$requestObserver instanceof RequestObserver) {
            return $this->frameworkStack->around($router, $afterSession)->handle($request);
        }

        $requestObserver->started($request);

        return $requestObserver->finished($request, $this->frameworkStack->around($router, $afterSession)->handle($request));
    }
}
