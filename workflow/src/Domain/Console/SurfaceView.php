<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain\Console;

use function in_array;
use function str_starts_with;

/**
 * `build/surface.json` as the inspect commands read it (#109): the routes,
 * commands, jobs and schedules with their modules, the modules, the kinds,
 * and whether a module manifest changed after the build wrote it.
 */
final readonly class SurfaceView
{
    /**
     * @param list<array{handler: string, kind: string, method: string, module: string, path: string}> $routes    The routes.
     * @param list<array{class: string, kind: string, module: string, name: string}>                   $commands  The commands.
     * @param list<array{class: string, kind: string, module: string, name: string}>                   $jobs      The jobs.
     * @param list<array{cadence: string, job: string, module: string, name: string}>                  $schedules The schedules.
     * @param list<SurfaceModule>                                                                      $modules   The modules.
     * @param list<SurfaceKind>                                                                        $kinds     The kinds.
     * @param bool                                                                                     $stale     Whether manifests changed.
     */
    public function __construct(
        public array $routes,
        public array $commands,
        public array $jobs,
        public array $schedules,
        public array $modules,
        public array $kinds,
        public bool $stale,
    ) {
    }

    /**
     * @param string $name A module's name.
     *
     * @return SurfaceModule|null It; null when the surface has no such module.
     */
    public function module(string $name): ?SurfaceModule
    {
        foreach ($this->modules as $module) {
            if ($module->name === $name) {
                return $module;
            }
        }

        return null;
    }

    /**
     * @param string $name A module's name.
     *
     * @return list<string> The modules that require it, in build order.
     */
    public function dependents(string $name): array
    {
        $dependents = [];

        foreach ($this->modules as $module) {
            if (!in_array($name, $module->requires, true)) {
                continue;
            }

            $dependents[] = $module->name;
        }

        return $dependents;
    }

    /**
     * @param string $class A class.
     *
     * @return string The module whose namespace holds it; empty when none does.
     */
    public function owner(string $class): string
    {
        foreach ($this->modules as $module) {
            if (str_starts_with($class, $module->namespace)) {
                return $module->name;
            }
        }

        return '';
    }
}
