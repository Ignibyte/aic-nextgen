<?php

declare(strict_types=1);

namespace Gates\Application;

use Gates\Domain\ModuleDefinition;
use Gates\Domain\WiredObject;
use InvalidArgumentException;

use function ucfirst;

/**
 * Writes the generated module wiring under `app/Generated/` (#92): for each
 * module its own `<M>Routes`, `<M>Handlers`, `<M>Provider` and `<M>Migrations`
 * (each only when it has something to hold, and importing only that module's
 * classes, so two modules' same-named classes never meet), then the four
 * aggregators the app calls, `ModuleRoutes`, `ModuleHandlers`, `ModuleProvider`
 * and `ModuleMigrations`, each chained in parts so any number of modules fits.
 *
 * The code is straight-line in the native style: constructors and literal
 * arrays, no loops over objects, no static calls, `(new X())->m()` with its
 * parentheses for PDepend. It is committed and gated like hand-written code,
 * so a module set the generator cannot write within the gates' limits is
 * refused, never written loose.
 */
final readonly class WiringWriter
{
    /**
     * @param RoutesSource         $routesSource         Writes `ModuleRoutes`.
     * @param ModuleHandlersSource $moduleHandlersSource Writes `ModuleHandlers`.
     * @param HandlerSource        $handlerSource        Writes each module's handlers.
     * @param ProviderSource       $providerSource       Writes `ModuleProvider`.
     * @param MigrationsSource     $migrationsSource     Writes `ModuleMigrations`.
     */
    public function __construct(
        private RoutesSource $routesSource = new RoutesSource(),
        private ModuleHandlersSource $moduleHandlersSource = new ModuleHandlersSource(),
        private HandlerSource $handlerSource = new HandlerSource(),
        private ProviderSource $providerSource = new ProviderSource(),
        private MigrationsSource $migrationsSource = new MigrationsSource(),
    ) {
    }

    /**
     * @param list<ModuleDefinition>           $modules     The modules in build order.
     * @param array<string, list<WiredObject>> $controllers Each module's wired controllers, in route order, by module name.
     *
     * @throws InvalidArgumentException When the modules do not fit the generated classes.
     *
     * @return array<string, string> The source of each generated file, by file name in `app/Generated/`.
     */
    public function files(array $modules, array $controllers): array
    {
        $files = [];
        $members = ['handlers' => [], 'migrations' => [], 'provider' => [], 'routes' => []];

        foreach ($modules as $module) {
            foreach ($this->moduleFiles($module, $controllers[$module->name] ?? []) as $kind => $source) {
                $class = $module->studlyName() . ucfirst($kind);
                $members[$kind][] = $class;
                $files[$class . '.php'] = $source;
            }
        }

        return [
            ...$files,
            ...$this->moduleHandlersSource->aggregate($members['handlers']),
            ...$this->migrationsSource->aggregate($members['migrations']),
            ...$this->providerSource->aggregate($members['provider']),
            ...$this->routesSource->aggregate($members['routes']),
        ];
    }

    /**
     * One module's own generated classes, each only when the module has something for it to hold.
     *
     * @param ModuleDefinition  $moduleDefinition The module.
     * @param list<WiredObject> $controllers      Its wired controllers, in route order.
     *
     * @throws InvalidArgumentException When one of its classes would not fit the gates.
     *
     * @return array<string, string> Each class's source, by kind: `handlers`, `migrations`, `provider`, `routes`.
     */
    private function moduleFiles(ModuleDefinition $moduleDefinition, array $controllers): array
    {
        $module = $moduleDefinition->module;
        $files = [];

        if ($controllers !== []) {
            $files['handlers'] = $this->handlerSource->source($moduleDefinition, $controllers);
            $files['routes'] = $this->routesSource->module($moduleDefinition);
        }

        if ($module->migrations() !== []) {
            $files['migrations'] = $this->migrationsSource->module($moduleDefinition);
        }

        if ($module->blocks() !== [] || $module->plugins() !== []) {
            $files['provider'] = $this->providerSource->module($moduleDefinition);
        }

        return $files;
    }
}
