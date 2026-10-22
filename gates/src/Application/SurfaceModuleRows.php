<?php

declare(strict_types=1);

namespace Gates\Application;

use AIC\Module\Contracts\Binding;
use AIC\Module\Contracts\ModuleCommand;
use AIC\Module\Contracts\ModuleJob;
use AIC\Module\Contracts\ModuleMiddleware;
use AIC\Module\Contracts\ModuleSchedule;
use AIC\Module\Contracts\ModuleSection;
use AIC\Module\Contracts\PluginTag;
use Closure;
use Gates\Domain\ModuleDefinition;

use function array_map;

/**
 * The surface's module rows and the owners of its named entries (#92, #109):
 * what each module declares, keys in order, and which module declares each
 * command, job and schedule; the framework's own read as `framework`.
 */
final readonly class SurfaceModuleRows
{
    /**
     * @param ModuleDefinition $moduleDefinition A module.
     *
     * @return array<string, list<array<string, string>>|list<string>|string> What it declares, keys in order.
     */
    public function row(ModuleDefinition $moduleDefinition): array
    {
        return [
            'bindings' => self::bindings($moduleDefinition),
            'blocks' => $moduleDefinition->entries(ModuleSection::Blocks),
            'exports' => $moduleDefinition->entries(ModuleSection::Exports),
            'factories' => $moduleDefinition->entries(ModuleSection::Factories),
            'middleware' => self::middleware($moduleDefinition),
            'migrations' => $moduleDefinition->entries(ModuleSection::Migrations),
            'name' => $moduleDefinition->name,
            'namespace' => $moduleDefinition->namespace,
            'plugins' => self::plugins($moduleDefinition),
            'prefixes' => $moduleDefinition->entries(ModuleSection::ProtectedPrefixes),
            'requires' => $moduleDefinition->entries(ModuleSection::Requires),
        ];
    }

    /**
     * @param list<ModuleDefinition> $modules The modules.
     *
     * @return array{commands: array<string, string>, jobs: array<string, string>, schedules: array<string, string>} The module
     *                                                                                                               that declares
     *                                                                                                               each, by name.
     */
    public function owners(array $modules): array
    {
        return [
            'commands' => self::named($modules, static fn (ModuleDefinition $moduleDefinition): array => array_map(
                static fn (ModuleCommand $moduleCommand): string => $moduleCommand->name,
                $moduleDefinition->declared(ModuleCommand::class),
            )),
            'jobs' => self::named($modules, static fn (ModuleDefinition $moduleDefinition): array => array_map(
                static fn (ModuleJob $moduleJob): string => $moduleJob->name,
                $moduleDefinition->declared(ModuleJob::class),
            )),
            'schedules' => self::named($modules, static fn (ModuleDefinition $moduleDefinition): array => array_map(
                static fn (ModuleSchedule $moduleSchedule): string => $moduleSchedule->name,
                $moduleDefinition->declared(ModuleSchedule::class),
            )),
        ];
    }

    /**
     * @param list<ModuleDefinition>                  $modules The modules.
     * @param Closure(ModuleDefinition): list<string> $names   The names one module declares.
     *
     * @return array<string, string> The module that declares each name.
     */
    private static function named(array $modules, Closure $names): array
    {
        $owners = [];

        foreach ($modules as $module) {
            foreach ($names($module) as $name) {
                $owners[$name] = $module->name;
            }
        }

        return $owners;
    }

    /**
     * @param ModuleDefinition $moduleDefinition A module.
     *
     * @return list<array<string, string>> Its bindings: the class a constructor gets for each interface.
     */
    private static function bindings(ModuleDefinition $moduleDefinition): array
    {
        return array_map(
            static fn (Binding $binding): array => ['class' => $binding->class, 'interface' => $binding->interface],
            $moduleDefinition->declared(Binding::class),
        );
    }

    /**
     * @param ModuleDefinition $moduleDefinition A module.
     *
     * @return list<array<string, string>> Its middleware, each with its position.
     */
    private static function middleware(ModuleDefinition $moduleDefinition): array
    {
        return array_map(
            static fn (ModuleMiddleware $moduleMiddleware): array => [
                'class' => $moduleMiddleware->middleware,
                'position' => $moduleMiddleware->position->name,
            ],
            $moduleDefinition->declared(ModuleMiddleware::class),
        );
    }

    /**
     * @param ModuleDefinition $moduleDefinition A module.
     *
     * @return list<array<string, string>> Its plugins, each with its type.
     */
    private static function plugins(ModuleDefinition $moduleDefinition): array
    {
        return array_map(
            static fn (PluginTag $pluginTag): array => ['class' => $pluginTag->class, 'type' => $pluginTag->type],
            $moduleDefinition->declared(PluginTag::class),
        );
    }
}
