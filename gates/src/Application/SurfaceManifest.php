<?php

declare(strict_types=1);

namespace Gates\Application;

use AIC\Controller\Contracts\Command;
use AIC\Controller\Contracts\Job;
use AIC\Module\Contracts\ModuleRoute;
use AIC\Module\Contracts\ModuleSection;
use AIC\Module\Contracts\PluginTag;
use AIC\Routing\Domain\Route;
use AIC\Schedule\Domain\Schedule;
use Gates\Domain\ClassKinds;
use Gates\Domain\ModuleDefinition;
use JsonException;

use function array_map;
use function json_encode;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * The app's surface as one JSON document (`build/surface.json`, #26): every
 * route with its method, path, handler and kind, and every console command
 * with its name, class and kind, and every queue job with its name, class and
 * kind, and every schedule with its cadence and job, in declaration order. A kind is read from the
 * class's interfaces, so it is never declared twice. Each route names the module that declares it
 * (#92, #93: every route is a module's), and the modules follow in build order with what each declares.
 */
final readonly class SurfaceManifest
{
    /**
     * @param ClassKinds $classKinds Reads each entry point's kind.
     */
    public function __construct(
        private ClassKinds $classKinds = new ClassKinds(),
    ) {
    }

    /**
     * @param list<Route>            $routes    The routes, as declared.
     * @param array<string, Command> $commands  The console commands by name.
     * @param array<string, Job>     $jobs      The queue's jobs by name (#46).
     * @param list<Schedule>         $schedules The schedules (#47).
     * @param list<ModuleDefinition> $modules   The modules, in build order (#92).
     *
     * @throws JsonException When the manifest cannot be encoded.
     *
     * @return string The manifest, pretty-printed, with a final newline.
     */
    public function json(array $routes, array $commands, array $jobs, array $schedules, array $modules): string
    {
        $manifest = ['routes' => $this->routes($routes, $modules), 'commands' => $this->classes($commands)];
        $manifest['jobs'] = $this->classes($jobs);
        $manifest['schedules'] = [];

        foreach ($schedules as $schedule) {
            $manifest['schedules'][] = ['cadence' => $schedule->cadence->expression, 'job' => $schedule->job, 'name' => $schedule->name];
        }

        $manifest['modules'] = array_map($this->module(...), $modules);

        return json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
    }

    /**
     * @param array<string, Command|Job> $entryPoints Commands or jobs by name.
     *
     * @return list<array<string, string>> One row each: its class, its kind and its name.
     */
    private function classes(array $entryPoints): array
    {
        $rows = [];

        foreach ($entryPoints as $name => $entryPoint) {
            $rows[] = ['class' => $entryPoint::class, 'kind' => $this->classKinds->entryKind($entryPoint::class), 'name' => $name];
        }

        return $rows;
    }

    /**
     * @param list<Route>            $routes  The routes, as declared.
     * @param list<ModuleDefinition> $modules The modules, in build order.
     *
     * @return list<array<string, string>> One row per route, with the module that declares it.
     */
    private function routes(array $routes, array $modules): array
    {
        $moduleOf = [];

        foreach ($modules as $module) {
            foreach ($module->declared(ModuleRoute::class) as $moduleRoute) {
                $moduleOf[$moduleRoute->handler] = $module->name;
            }
        }

        return array_map(fn (Route $route): array => [
            'handler' => $route->handler,
            'kind' => $this->classKinds->entryKind($route->handler),
            'method' => $route->method->value,
            'module' => $moduleOf[$route->handler] ?? '',
            'path' => $route->path,
        ], $routes);
    }

    /**
     * @param ModuleDefinition $moduleDefinition A module.
     *
     * @return array<string, list<array<string, string>>|list<string>|string> What it declares, keys in order.
     */
    private function module(ModuleDefinition $moduleDefinition): array
    {
        $plugins = [];

        foreach ($moduleDefinition->declared(PluginTag::class) as $pluginTag) {
            $plugins[] = ['class' => $pluginTag->class, 'type' => $pluginTag->type];
        }

        return [
            'blocks' => $moduleDefinition->entries(ModuleSection::Blocks),
            'exports' => $moduleDefinition->entries(ModuleSection::Exports),
            'migrations' => $moduleDefinition->entries(ModuleSection::Migrations),
            'name' => $moduleDefinition->name,
            'namespace' => $moduleDefinition->namespace,
            'plugins' => $plugins,
            'requires' => $moduleDefinition->entries(ModuleSection::Requires),
        ];
    }
}
