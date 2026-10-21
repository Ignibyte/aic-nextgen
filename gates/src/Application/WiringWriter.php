<?php

declare(strict_types=1);

namespace Gates\Application;

use Gates\Domain\ModuleDefinition;
use Gates\Domain\WiredObject;
use InvalidArgumentException;

/**
 * Writes the generated module wiring under `app/Generated/` (#92): the routes,
 * the handlers (one class per module with routes, merged by
 * `ModuleHandlers`), the blocks and plugins, and the migrations.
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
        $handlerClasses = [];

        foreach ($modules as $module) {
            if (($controllers[$module->name] ?? []) === []) {
                continue;
            }

            $handlerClasses[] = $module->studlyName() . 'Handlers';
            $files[$module->studlyName() . 'Handlers.php'] = $this->handlerSource->source($module, $controllers[$module->name]);
        }

        return [
            'ModuleHandlers.php' => $this->moduleHandlersSource->source($handlerClasses),
            'ModuleMigrations.php' => $this->migrationsSource->source($modules),
            'ModuleProvider.php' => $this->providerSource->source($modules),
            'ModuleRoutes.php' => $this->routesSource->source($modules),
        ] + $files;
    }
}
