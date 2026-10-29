<?php

declare(strict_types=1);

namespace Gates\Application;

use AIC\Module\Contracts\ModuleSchedule;
use AIC\Module\Contracts\ModuleSection;
use AIC\Module\Contracts\PluginTag;
use Gates\Domain\ModuleDefinition;
use InvalidArgumentException;

/**
 * Writes one module's own generated classes that hold no context (#92, #93),
 * each only when the module has something for it: by kind suffix, `Provider`,
 * `Migrations`, `Schedules` and `Prefixes`.
 */
final readonly class ModuleDataClasses
{
    /**
     * @param ProviderSource   $providerSource   The blocks and plugins.
     * @param MigrationsSource $migrationsSource The migrations.
     * @param ModuleDataSource $moduleDataSource The schedules and prefixes.
     */
    public function __construct(
        private ProviderSource $providerSource = new ProviderSource(),
        private MigrationsSource $migrationsSource = new MigrationsSource(),
        private ModuleDataSource $moduleDataSource = new ModuleDataSource(),
    ) {
    }

    /**
     * @param ModuleDefinition $moduleDefinition A module.
     *
     * @throws InvalidArgumentException When a class would not fit the gates.
     *
     * @return array<string, string> Its provider, migrations, schedules and prefixes, each when it has them.
     */
    public function files(ModuleDefinition $moduleDefinition): array
    {
        $files = [];

        if ($moduleDefinition->declared(PluginTag::class) !== []) {
            $files['Provider'] = $this->providerSource->module($moduleDefinition);
        }

        if ($moduleDefinition->entries(ModuleSection::Migrations) !== []) {
            $files['Migrations'] = $this->migrationsSource->module($moduleDefinition);
        }

        if ($moduleDefinition->declared(ModuleSchedule::class) !== []) {
            $files['Schedules'] = $this->moduleDataSource->schedules($moduleDefinition);
        }

        if ($moduleDefinition->entries(ModuleSection::ProtectedPrefixes) !== []) {
            $files['Prefixes'] = $this->moduleDataSource->prefixes($moduleDefinition);
        }

        return $files;
    }
}
