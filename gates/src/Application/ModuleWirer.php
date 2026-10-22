<?php

declare(strict_types=1);

namespace Gates\Application;

use AIC\Module\Contracts\MiddlewarePosition;
use AIC\Module\Contracts\ModuleCommand;
use AIC\Module\Contracts\ModuleJob;
use AIC\Module\Contracts\ModuleMiddleware;
use AIC\Module\Contracts\ModuleRoute;
use Gates\Domain\ModuleDefinition;
use Gates\Domain\WiredModule;
use Gates\Domain\WiredObject;
use InvalidArgumentException;

use function array_map;
use function array_unique;
use function array_values;

/**
 * Wires every module's declarations (#92, #93): its route controllers and
 * middleware against the web side's roots, its commands and jobs against the
 * console's.
 */
final readonly class ModuleWirer
{
    /**
     * @param Autowirer      $autowirer      Resolves a constructor.
     * @param WiringScopes   $wiringScopes   Builds each module's scope.
     * @param WiringContexts $wiringContexts The two sides' roots.
     */
    public function __construct(
        private Autowirer $autowirer = new Autowirer(),
        private WiringScopes $wiringScopes = new WiringScopes(),
        private WiringContexts $wiringContexts = new WiringContexts(),
    ) {
    }

    /**
     * @param list<ModuleDefinition> $modules The checked modules, in build order.
     *
     * @throws InvalidArgumentException When a declaration cannot be wired.
     *
     * @return list<WiredModule> Each module, wired.
     */
    public function wire(array $modules): array
    {
        $wired = [];

        foreach ($modules as $module) {
            $wired[] = $this->module($module, $modules);
        }

        return $wired;
    }

    /**
     * @param ModuleDefinition       $moduleDefinition A module.
     * @param list<ModuleDefinition> $modules          Every module.
     *
     * @throws InvalidArgumentException When a declaration cannot be wired.
     *
     * @return WiredModule The module, wired.
     */
    private function module(ModuleDefinition $moduleDefinition, array $modules): WiredModule
    {
        $wiringScope = $this->wiringScopes->scope($moduleDefinition, $modules, $this->wiringContexts->web());
        [$commands, $jobs] = $this->console($moduleDefinition, $modules);
        $wire = fn (string $class): WiredObject => $this->autowirer->wire($class, $wiringScope);
        $routes = $moduleDefinition->declared(ModuleRoute::class);
        $handlers = array_map(static fn (ModuleRoute $moduleRoute): string => $moduleRoute->handler, $routes);
        $controllers = array_map($wire, array_values(array_unique($handlers)));
        $middleware = [];

        foreach ($moduleDefinition->declared(ModuleMiddleware::class) as $moduleMiddleware) {
            $middleware[] = match ($moduleMiddleware->position) {
                MiddlewarePosition::AfterSession => $wire($moduleMiddleware->middleware),
            };
        }

        return new WiredModule($moduleDefinition, $controllers, $middleware, $commands, $jobs);
    }

    /**
     * @param ModuleDefinition       $moduleDefinition A module.
     * @param list<ModuleDefinition> $modules          Every module.
     *
     * @throws InvalidArgumentException When a command or job cannot be wired.
     *
     * @return array{array<string, WiredObject>, array<string, WiredObject>} Its commands and its jobs, wired, by name.
     */
    private function console(ModuleDefinition $moduleDefinition, array $modules): array
    {
        $wiringScope = $this->wiringScopes->scope($moduleDefinition, $modules, $this->wiringContexts->console());
        $commands = [];
        $jobs = [];

        foreach ($moduleDefinition->declared(ModuleCommand::class) as $moduleCommand) {
            $commands[$moduleCommand->name] = $this->autowirer->wire($moduleCommand->command, $wiringScope);
        }

        foreach ($moduleDefinition->declared(ModuleJob::class) as $moduleJob) {
            $jobs[$moduleJob->name] = $this->autowirer->wire($moduleJob->job, $wiringScope);
        }

        return [$commands, $jobs];
    }
}
