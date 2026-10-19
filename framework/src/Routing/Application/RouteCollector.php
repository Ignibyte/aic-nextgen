<?php

declare(strict_types=1);

namespace AIC\Routing\Application;

use AIC\Routing\Domain\DuplicateRouteException;
use AIC\Routing\Domain\Method;
use AIC\Routing\Domain\Route;
use AIC\Routing\Domain\RoutePatternCompiler;
use AIC\Routing\Domain\RouteTable;
use LogicException;

/**
 * Declares the app's routes, in `config/routes.php`, and compiles them to a
 * RouteTable. Immutable: each declaration returns a new collector.
 *
 * The GET declaration is `onGet()`, not `get()`: elephc resolves its WeakMap
 * prelude's `WeakReference::get()` call against a user method of that name that
 * returns an object (carry #24).
 */
final readonly class RouteCollector
{
    /**
     * @param RoutePatternCompiler $routePatternCompiler Compiles each path pattern.
     * @param list<object>         $routes               The routes declared so far, in order.
     */
    public function __construct(
        private RoutePatternCompiler $routePatternCompiler,
        private array $routes = [],
    ) {
    }

    /**
     * Declare a GET route; it answers HEAD too.
     *
     * @param string $path    The path pattern, such as `/hello/{name}`.
     * @param string $handler The id of its handler.
     *
     * @throws DuplicateRouteException When GET is already declared for the pattern.
     *
     * @return self A collector with the route added.
     */
    public function onGet(string $path, string $handler): self
    {
        $route = new Route(Method::GET, $path, $handler);

        foreach ($this->routes as $declared) {
            if ($declared instanceof Route && $declared->method === $route->method && $declared->path === $path) {
                throw new DuplicateRouteException($route);
            }
        }

        return new self($this->routePatternCompiler, [...$this->routes, $route]);
    }

    /**
     * @throws LogicException When a path pattern does not compile.
     *
     * @return RouteTable The declared routes, compiled.
     */
    public function table(): RouteTable
    {
        $compiled = [];

        foreach ($this->routes as $route) {
            if (!($route instanceof Route)) {
                continue;
            }

            $compiled[] = $this->routePatternCompiler->compile($route);
        }

        return new RouteTable($compiled);
    }
}
