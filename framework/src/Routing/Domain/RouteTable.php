<?php

declare(strict_types=1);

namespace AIC\Routing\Domain;

use LogicException;

use function in_array;

use const PHP_INT_MAX;

/**
 * The compiled routes, and the match of a request's method and path to one.
 *
 * Of the routes whose pattern fits the path and that answer the method, the one
 * with the fewest parameters wins; ties go to the first declared. A path no
 * pattern fits, or fits only for other methods, throws.
 *
 * The route lists are typed `list<object>` and narrowed with `instanceof` where
 * an element is read: elephc types a list element as `mixed`, and an inline
 * `instanceof` is what narrows it (plan.md, native style).
 */
final readonly class RouteTable
{
    /**
     * @param list<object> $routes The compiled routes, in declaration order.
     */
    public function __construct(
        private array $routes,
    ) {
    }

    /**
     * @param string $method The request method.
     * @param string $path   The request path.
     *
     * @throws RouteNotFoundException    When no route's pattern fits the path.
     * @throws MethodNotAllowedException When the fitting routes answer other methods only.
     *
     * @return RouteMatch The matched route and its parameters.
     */
    public function match(string $method, string $path): RouteMatch
    {
        $fitting = $this->fitting($path);

        if ($fitting === []) {
            throw new RouteNotFoundException($path);
        }

        $answering = self::answering($fitting, $method);

        if ($answering === []) {
            throw new MethodNotAllowedException($path, self::allowed($fitting));
        }

        $compiledRoute = self::mostSpecific($answering);

        return new RouteMatch($compiledRoute->route, $compiledRoute->parametersOf($path));
    }

    /**
     * @return list<string> Every route's handler id, in declaration order.
     */
    public function handlers(): array
    {
        $handlers = [];

        foreach ($this->routes() as $route) {
            $handlers[] = $route->handler;
        }

        return $handlers;
    }

    /**
     * @return list<Route> Every route as declared, in declaration order (the build's surface
     *                     manifest reads them).
     */
    public function routes(): array
    {
        $routes = [];

        foreach ($this->routes as $route) {
            if (!($route instanceof CompiledRoute)) {
                continue;
            }

            $routes[] = $route->route;
        }

        return $routes;
    }

    /**
     * @param string $path The request path.
     *
     * @return list<object> The compiled routes whose pattern fits it.
     */
    private function fitting(string $path): array
    {
        $fitting = [];

        foreach ($this->routes as $route) {
            if (!($route instanceof CompiledRoute) || !$route->matches($path)) {
                continue;
            }

            $fitting[] = $route;
        }

        return $fitting;
    }

    /**
     * @param list<object> $fitting The compiled routes whose pattern fits the path.
     * @param string       $method  The request method.
     *
     * @return list<object> Those that answer it.
     */
    private static function answering(array $fitting, string $method): array
    {
        $answering = [];

        foreach ($fitting as $route) {
            if (!($route instanceof CompiledRoute) || !$route->route->method->answers($method)) {
                continue;
            }

            $answering[] = $route;
        }

        return $answering;
    }

    /**
     * @param list<object> $answering At least one compiled route.
     *
     * @throws LogicException When the list holds no compiled route.
     *
     * @return CompiledRoute The one with the fewest parameters; the first of a tie.
     */
    private static function mostSpecific(array $answering): CompiledRoute
    {
        $bestIndex = 0;
        $bestCount = PHP_INT_MAX;

        foreach ($answering as $index => $route) {
            if (!($route instanceof CompiledRoute) || $route->parameterCount() >= $bestCount) {
                continue;
            }

            $bestIndex = $index;
            $bestCount = $route->parameterCount();
        }

        $best = $answering[$bestIndex] ?? null;

        if (!$best instanceof CompiledRoute) {
            throw new LogicException('No compiled route to choose from.');
        }

        return $best;
    }

    /**
     * @param list<object> $fitting The compiled routes whose pattern fits the path.
     *
     * @return list<string> The methods they answer, once each.
     */
    private static function allowed(array $fitting): array
    {
        $allowed = self::noMethods();

        foreach ($fitting as $route) {
            if (!($route instanceof CompiledRoute)) {
                continue;
            }

            $allowed = self::withMethods($allowed, $route->route->method);
        }

        return $allowed;
    }

    /**
     * A typed empty start for the loop in allowed(): elephc types a `[]` that a
     * loop reassigns as `mixed` (plan.md, native style).
     *
     * @return list<string> No methods.
     */
    private static function noMethods(): array
    {
        return [];
    }

    /**
     * @param list<string> $allowed The methods so far.
     * @param Method       $method  A fitting route's method.
     *
     * @return list<string> With the methods it answers added, once each.
     */
    private static function withMethods(array $allowed, Method $method): array
    {
        foreach ($method->answered() as $answered) {
            if (in_array($answered, $allowed, true)) {
                continue;
            }

            $allowed[] = $answered;
        }

        return $allowed;
    }
}
