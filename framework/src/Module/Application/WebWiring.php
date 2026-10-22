<?php

declare(strict_types=1);

namespace AIC\Module\Application;

use AIC\Routing\Application\RouteCollector;
use AIC\Routing\Domain\DuplicateRouteException;
use AIC\Schedule\Domain\Schedule;

/**
 * What the app kernel always takes from the generated wiring (#93): the
 * modules' routes, schedules and protected prefixes. `bin/aic-build` writes the
 * one implementation, `App\Generated\WebModules`, which also implements
 * `PluginWiring`, `HandlerWiring` and `MiddlewareWiring` when some module has
 * plugins, routes or middleware.
 */
interface WebWiring
{
    /**
     * @param RouteCollector $routeCollector The routes so far.
     *
     * @throws DuplicateRouteException When a module's route repeats one already declared.
     *
     * @return RouteCollector The collector with the modules' routes.
     */
    public function routes(RouteCollector $routeCollector): RouteCollector;

    /**
     * @return list<Schedule> The modules' schedules.
     */
    public function schedules(): array;

    /**
     * @return list<string> The modules' protected path prefixes.
     */
    public function protectedPrefixes(): array;
}
