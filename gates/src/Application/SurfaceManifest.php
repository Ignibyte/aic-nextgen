<?php

declare(strict_types=1);

namespace Gates\Application;

use AIC\Controller\Contracts\Command;
use AIC\Controller\Contracts\Job;
use AIC\Module\Contracts\ModuleRoute;
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
 * Each command, job and schedule names its module, `framework` for the framework's own, and a
 * `kinds` table closes it (#109).
 */
final readonly class SurfaceManifest
{
    /**
     * @param ClassKinds        $classKinds        Reads each entry point's kind.
     * @param SurfaceModuleRows $surfaceModuleRows The module rows and the owners of named entries (#109).
     * @param SurfaceKinds      $surfaceKinds      The kinds table (#109).
     */
    public function __construct(
        private ClassKinds $classKinds = new ClassKinds(),
        private SurfaceModuleRows $surfaceModuleRows = new SurfaceModuleRows(),
        private SurfaceKinds $surfaceKinds = new SurfaceKinds(),
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
        $owners = $this->surfaceModuleRows->owners($modules);
        $manifest = ['routes' => $this->routes($routes, $modules), 'commands' => $this->classes($commands, $owners['commands'])];
        $manifest['jobs'] = $this->classes($jobs, $owners['jobs']);
        $manifest['schedules'] = [];

        foreach ($schedules as $schedule) {
            $manifest['schedules'][] = [
                'cadence' => $schedule->cadence->expression,
                'job' => $schedule->job,
                'module' => $owners['schedules'][$schedule->name] ?? 'framework',
                'name' => $schedule->name,
            ];
        }

        $manifest['modules'] = array_map($this->surfaceModuleRows->row(...), $modules);
        $manifest['kinds'] = $this->surfaceKinds->rows();

        return json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
    }

    /**
     * @param array<string, Command|Job> $entryPoints Commands or jobs by name.
     * @param array<string, string>      $owners      The module that declares each, by name.
     *
     * @return list<array<string, string>> One row each: its class, its kind, its module (`framework` for the framework's) and its name.
     */
    private function classes(array $entryPoints, array $owners): array
    {
        $rows = [];

        foreach ($entryPoints as $name => $entryPoint) {
            $rows[] = [
                'class' => $entryPoint::class,
                'kind' => $this->classKinds->entryKind($entryPoint::class),
                'module' => $owners[$name] ?? 'framework',
                'name' => $name,
            ];
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
}
