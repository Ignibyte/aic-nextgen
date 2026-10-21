<?php

declare(strict_types=1);

namespace Gates\Application;

use AIC\Module\Contracts\ModuleRoute;
use AIC\Module\Contracts\RouteMethod;
use AIC\Routing\Application\RouteCollector;
use AIC\Routing\Domain\DuplicateRouteException;
use Gates\Domain\ModuleDefinition;
use Gates\Domain\WiredObject;
use Gates\Domain\WiringScope;
use InvalidArgumentException;

use function array_map;
use function in_array;

/**
 * The module build `bin/aic-build` runs (#92): read every manifest, check it,
 * order the modules by what they require, wire each route's controller, and
 * give back the generated files and the routes on the app's collector.
 *
 * Until #93 moves the demo into modules, a module set the generator cannot
 * write gate-clean is refused: no route in any module, or no block or plugin
 * in any.
 */
final readonly class ModuleBuild
{
    /**
     * @param string            $root              The repo root.
     * @param ModuleReader      $moduleReader      Reads the manifests.
     * @param ModuleGraph       $moduleGraph       Orders the modules.
     * @param ModuleChecks      $moduleChecks      Checks each module as a whole.
     * @param DeclarationChecks $declarationChecks Checks what each manifest declares.
     * @param Autowirer         $autowirer         Wires each controller.
     * @param WiringWriter      $wiringWriter      Writes the generated classes.
     * @param QuietFileReader   $quietFileReader   Reads composer.json and phpcs.xml.dist.
     */
    public function __construct(
        private string $root,
        private ModuleReader $moduleReader = new ModuleReader(),
        private ModuleGraph $moduleGraph = new ModuleGraph(),
        private ModuleChecks $moduleChecks = new ModuleChecks(),
        private DeclarationChecks $declarationChecks = new DeclarationChecks(),
        private Autowirer $autowirer = new Autowirer(),
        private WiringWriter $wiringWriter = new WiringWriter(),
        private QuietFileReader $quietFileReader = new QuietFileReader(),
    ) {
    }

    /**
     * @throws InvalidArgumentException When a module is refused.
     *
     * @return list<ModuleDefinition> The checked modules, in build order.
     */
    public function modules(): array
    {
        $modules = $this->moduleGraph->order($this->moduleReader->read($this->root));
        $composer = $this->quietFileReader->read($this->root . '/composer.json') ?? '';
        $phpcs = $this->quietFileReader->read($this->root . '/phpcs.xml.dist') ?? '';

        foreach ($modules as $module) {
            $this->moduleChecks->check($module, $this->root, $composer, $phpcs);
            $this->declarationChecks->check($module);
        }

        return $modules;
    }

    /**
     * @param list<ModuleDefinition> $modules The checked modules, in build order.
     *
     * @throws InvalidArgumentException When a controller cannot be wired, or the set cannot be generated gate-clean.
     *
     * @return array<string, string> The source of each generated file, by absolute path.
     */
    public function files(array $modules): array
    {
        $namespaces = array_map(static fn (ModuleDefinition $moduleDefinition): string => $moduleDefinition->namespace, $modules);
        $controllers = [];

        foreach ($modules as $module) {
            $wiringScope = $this->scope($module, $modules, $namespaces);
            $controllers[$module->name] = array_map(
                fn (ModuleRoute $moduleRoute): WiredObject => $this->autowirer->wire($moduleRoute->handler, $wiringScope),
                $module->module->routes(),
            );
        }

        $files = [];

        foreach ($this->wiringWriter->files($modules, $controllers) as $file => $source) {
            $files[$this->root . '/app/Generated/' . $file] = $source;
        }

        return $files;
    }

    /**
     * Add the modules' routes to the app's, as the generated `ModuleRoutes` does at run time.
     *
     * @param list<ModuleDefinition> $modules        The checked modules, in build order.
     * @param RouteCollector         $routeCollector The app's routes from `config/routes.php`.
     *
     * @throws InvalidArgumentException When a module's route repeats one already declared.
     *
     * @return RouteCollector The collector with the modules' routes.
     */
    public function routes(array $modules, RouteCollector $routeCollector): RouteCollector
    {
        foreach ($modules as $module) {
            foreach ($module->module->routes() as $moduleRoute) {
                $routeCollector = $this->route($module, $moduleRoute, $routeCollector);
            }
        }

        return $routeCollector;
    }

    /**
     * @param ModuleDefinition $moduleDefinition The module declaring the route.
     * @param ModuleRoute      $moduleRoute      The route.
     * @param RouteCollector   $routeCollector   The routes so far.
     *
     * @throws InvalidArgumentException When the route repeats one already declared.
     *
     * @return RouteCollector The routes with this one.
     */
    private function route(
        ModuleDefinition $moduleDefinition,
        ModuleRoute $moduleRoute,
        RouteCollector $routeCollector,
    ): RouteCollector {
        try {
            return $moduleRoute->method === RouteMethod::Get
                ? $routeCollector->get($moduleRoute->path, $moduleRoute->handler)
                : $routeCollector->post($moduleRoute->path, $moduleRoute->handler);
        } catch (DuplicateRouteException $duplicateRouteException) {
            throw new InvalidArgumentException(
                'module ' . $moduleDefinition->name . ': ' . $duplicateRouteException->getMessage(),
                0,
                $duplicateRouteException,
            );
        }
    }

    /**
     * @param ModuleDefinition       $moduleDefinition The module to wire.
     * @param list<ModuleDefinition> $modules          Every module.
     * @param list<string>           $namespaces       Every module's namespace.
     *
     * @return WiringScope What its constructors may be wired from: itself and the modules it requires.
     */
    private function scope(ModuleDefinition $moduleDefinition, array $modules, array $namespaces): WiringScope
    {
        $required = $moduleDefinition->module->requires();
        $visible = [];
        $bindings = [];

        foreach ($modules as $module) {
            if ($module !== $moduleDefinition && !in_array($module->name, $required, true)) {
                continue;
            }

            $visible[] = $module->namespace;
            $bindings = [...$bindings, ...$this->bindings($module)];
        }

        return new WiringScope($moduleDefinition, $visible, $namespaces, $bindings);
    }

    /**
     * @param ModuleDefinition $moduleDefinition A module.
     *
     * @return array<string, class-string> Its bound classes by interface.
     */
    private function bindings(ModuleDefinition $moduleDefinition): array
    {
        $bindings = [];

        foreach ($moduleDefinition->module->services() as $binding) {
            $bindings[$binding->interface] = $binding->class;
        }

        return $bindings;
    }
}
