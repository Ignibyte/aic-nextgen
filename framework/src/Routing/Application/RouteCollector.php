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
 * Collects the app's routes, which the generated wiring adds from the module
 * manifests (#93), and compiles them to a RouteTable. Immutable: each declaration returns a new collector.
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
    public function get(string $path, string $handler): self
    {
        return $this->with(new Route(Method::GET, $path, $handler));
    }

    /**
     * Declare a POST route.
     *
     * @param string $path    The path pattern.
     * @param string $handler The id of its handler.
     *
     * @throws DuplicateRouteException When POST is already declared for the pattern.
     *
     * @return self A collector with the route added.
     */
    public function post(string $path, string $handler): self
    {
        return $this->with(new Route(Method::POST, $path, $handler));
    }

    /**
     * Declare a PUT route (#242).
     *
     * @param string $path    The path pattern.
     * @param string $handler The id of its handler.
     *
     * @throws DuplicateRouteException When PUT is already declared for the pattern.
     *
     * @return self A collector with the route added.
     */
    public function put(string $path, string $handler): self
    {
        return $this->with(new Route(Method::PUT, $path, $handler));
    }

    /**
     * Declare a PATCH route (#242).
     *
     * @param string $path    The path pattern.
     * @param string $handler The id of its handler.
     *
     * @throws DuplicateRouteException When PATCH is already declared for the pattern.
     *
     * @return self A collector with the route added.
     */
    public function patch(string $path, string $handler): self
    {
        return $this->with(new Route(Method::PATCH, $path, $handler));
    }

    /**
     * Declare a DELETE route (#242).
     *
     * @param string $path    The path pattern.
     * @param string $handler The id of its handler.
     *
     * @throws DuplicateRouteException When DELETE is already declared for the pattern.
     *
     * @return self A collector with the route added.
     */
    public function delete(string $path, string $handler): self
    {
        return $this->with(new Route(Method::DELETE, $path, $handler));
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

    /**
     * @param Route $route A route to add.
     *
     * @throws DuplicateRouteException When its method is already declared for its pattern.
     *
     * @return self A collector with the route added.
     */
    private function with(Route $route): self
    {
        foreach ($this->routes as $declared) {
            if (
                $declared instanceof Route
                && $declared->method === $route->method
                && $declared->path === $route->path
            ) {
                throw new DuplicateRouteException($route);
            }
        }

        return new self($this->routePatternCompiler, [...$this->routes, $route]);
    }
}
