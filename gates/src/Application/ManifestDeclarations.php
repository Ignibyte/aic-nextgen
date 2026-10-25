<?php

declare(strict_types=1);

namespace Gates\Application;

use AIC\Module\Contracts\Binding;
use AIC\Module\Contracts\ModuleAsset;
use AIC\Module\Contracts\ModuleCommand;
use AIC\Module\Contracts\ModuleDeclaration;
use AIC\Module\Contracts\ModuleEntry;
use AIC\Module\Contracts\ModuleJob;
use AIC\Module\Contracts\ModuleManifest;
use AIC\Module\Contracts\ModuleMiddleware;
use AIC\Module\Contracts\ModuleRoute;
use AIC\Module\Contracts\ModuleSchedule;
use AIC\Module\Contracts\ModuleTemplate;
use AIC\Module\Contracts\ModuleTheme;
use AIC\Module\Contracts\PluginTag;
use InvalidArgumentException;

use function get_debug_type;
use function in_array;
use function str_starts_with;

/** Refuses declarations the build cannot consume instead of silently ignoring a public manifest entry. */
final readonly class ManifestDeclarations
{
    /** The exact declaration contracts supported by this build. */
    private const array TYPES = [
        Binding::class,
        ModuleAsset::class,
        ModuleCommand::class,
        ModuleEntry::class,
        ModuleJob::class,
        ModuleMiddleware::class,
        ModuleRoute::class,
        ModuleSchedule::class,
        ModuleTemplate::class,
        ModuleTheme::class,
        PluginTag::class,
    ];

    /**
     * @param ModuleManifest $moduleManifest The authored manifest.
     *
     * @throws InvalidArgumentException When a declaration has no supported build consumer.
     */
    public function check(ModuleManifest $moduleManifest): void
    {
        foreach ($moduleManifest->declarations as $moduleDeclaration) {
            $type = get_debug_type($moduleDeclaration);

            if (!in_array($type, self::TYPES, true)) {
                throw new InvalidArgumentException(
                    'module ' . $moduleManifest->name . ': unsupported declaration ' . $type . '.',
                );
            }

            $this->route($moduleManifest->name, $moduleDeclaration);
        }
    }

    /**
     * @param string            $owner             The declaring module.
     * @param ModuleDeclaration $moduleDeclaration Its supported declaration.
     *
     * @throws InvalidArgumentException When a module route shadows the framework's reserved asset namespace.
     */
    private function route(string $owner, ModuleDeclaration $moduleDeclaration): void
    {
        if (!$moduleDeclaration instanceof ModuleRoute) {
            return;
        }

        if ($moduleDeclaration->path === '/assets' || str_starts_with($moduleDeclaration->path, '/assets/')) {
            throw new InvalidArgumentException('module ' . $owner . ': /assets is reserved for the public asset catalog.');
        }
    }
}
