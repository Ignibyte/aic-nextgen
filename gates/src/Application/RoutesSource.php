<?php

declare(strict_types=1);

namespace Gates\Application;

use AIC\Module\Contracts\ModuleRoute;
use AIC\Module\Contracts\RouteMethod;
use AIC\Routing\Application\RouteCollector;
use AIC\Routing\Domain\DuplicateRouteException;
use Gates\Domain\ModuleDefinition;

use function strtr;

/**
 * Writes `App\Generated\ModuleRoutes` (#92): every module's routes, in build
 * order, chained onto the collector `config/routes.php` returns.
 */
final readonly class RoutesSource
{
    /**
     * The class, `{chain}` standing for the route calls.
     */
    private const string TEMPLATE = <<<'PHP'
        final readonly class ModuleRoutes
        {
            /**
             * @param RouteCollector $routeCollector The app's routes so far.
             *
             * @throws DuplicateRouteException When a module's route repeats one already declared.
             *
             * @return RouteCollector The collector, with the modules' routes after the app's.
             */
            public function add(RouteCollector $routeCollector): RouteCollector
            {
                return $routeCollector{chain};
            }
        }
        PHP;

    /**
     * @param PhpClassFile $phpClassFile Lays out the file.
     */
    public function __construct(
        private PhpClassFile $phpClassFile = new PhpClassFile(),
    ) {
    }

    /**
     * @param list<ModuleDefinition> $modules The modules in build order.
     *
     * @return string The class file.
     */
    public function source(array $modules): string
    {
        $classes = [RouteCollector::class, DuplicateRouteException::class];
        $chain = '';

        foreach ($modules as $module) {
            foreach ($module->module->routes() as $moduleRoute) {
                $classes[] = $moduleRoute->handler;
                $chain .= $this->call($moduleRoute);
            }
        }

        $docblock = $this->phpClassFile->generated('the module manifests', "the modules' routes, added after config/routes.php's.");

        return $this->phpClassFile->file('App\\Generated', $classes, [], $docblock, strtr(self::TEMPLATE, ['{chain}' => $chain]));
    }

    /**
     * @param ModuleRoute $moduleRoute A route.
     *
     * @return string Its call on the chain, on a line of its own.
     */
    private function call(ModuleRoute $moduleRoute): string
    {
        $method = $moduleRoute->method === RouteMethod::Get
            ? 'get'
            : 'post';

        $handler = $this->phpClassFile->short($moduleRoute->handler);

        return "\n            ->" . $method . "('" . $moduleRoute->path . "', " . $handler . '::class)';
    }
}
