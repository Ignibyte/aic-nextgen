<?php

declare(strict_types=1);

namespace Gates\Application;

use AIC\Module\Contracts\ModuleRoute;
use AIC\Module\Contracts\RouteMethod;
use AIC\Routing\Application\RouteCollector;
use AIC\Routing\Domain\DuplicateRouteException;
use Gates\Domain\ModuleDefinition;
use InvalidArgumentException;

/**
 * The module build `aic-build` runs (#92, #93): read every manifest, check
 * it and the set, order the modules by what they require, wire every
 * declaration, and give back the generated files under `generated/` and the
 * routes on the app's collector.
 */
final readonly class ModuleBuild
{
    /**
     * @param string            $root              The repo root.
     * @param ModuleReader      $moduleReader      Reads the manifests.
     * @param ModuleGraph       $moduleGraph       Orders the modules.
     * @param ModuleChecks      $moduleChecks      Checks each module as a whole.
     * @param DeclarationChecks $declarationChecks Checks what each manifest declares.
     * @param EntryChecks       $entryChecks       Checks each manifest's commands, jobs, middleware, prefixes and factories.
     * @param ModuleSetChecks   $moduleSetChecks   Checks the names across modules.
     * @param ModuleWirer       $moduleWirer       Wires each module's declarations.
     * @param WiringWriter      $wiringWriter      Writes the generated classes.
     * @param QuietFileReader   $quietFileReader   Reads composer.json and phpcs.xml.dist.
     */
    public function __construct(
        private string $root,
        private ModuleReader $moduleReader = new ModuleReader(),
        private ModuleGraph $moduleGraph = new ModuleGraph(),
        private ModuleChecks $moduleChecks = new ModuleChecks(),
        private DeclarationChecks $declarationChecks = new DeclarationChecks(),
        private EntryChecks $entryChecks = new EntryChecks(),
        private ModuleSetChecks $moduleSetChecks = new ModuleSetChecks(),
        private ModuleWirer $moduleWirer = new ModuleWirer(),
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
            $this->moduleChecks->check($module, $composer, $phpcs);
            $this->declarationChecks->check($module);
            $this->entryChecks->check($module);
        }

        $this->moduleSetChecks->check($modules);

        return $modules;
    }

    /**
     * @param list<ModuleDefinition> $modules The checked modules, in build order.
     *
     * @throws InvalidArgumentException When a controller cannot be wired, or the set cannot be generated gate-clean.
     *
     * @return array<string, string> The source of each generated file, by its name in the generated folder.
     */
    public function files(array $modules): array
    {
        return $this->wiringWriter->files($this->moduleWirer->wire($modules));
    }

    /**
     * Add the modules' routes to the app's, as the generated `ModuleRoutes` does at run time.
     *
     * @param list<ModuleDefinition> $modules        The checked modules, in build order.
     * @param RouteCollector         $routeCollector The routes so far; the build starts from an empty collector.
     *
     * @throws InvalidArgumentException When a module's route repeats one already declared.
     *
     * @return RouteCollector The collector with the modules' routes.
     */
    public function routes(array $modules, RouteCollector $routeCollector): RouteCollector
    {
        foreach ($modules as $module) {
            foreach ($module->declared(ModuleRoute::class) as $moduleRoute) {
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
}
