<?php

declare(strict_types=1);

namespace Gates\Application;

use AIC\Module\Contracts\ModuleManifest;
use Gates\Domain\ModuleDefinition;
use InvalidArgumentException;

use function basename;
use function glob;
use function in_array;
use function is_file;
use function preg_match;
use function sort;
use function str_replace;
use function ucwords;

use const GLOB_ONLYDIR;

/**
 * Reads the app's modules (#92): every folder under `modules/`, its manifest
 * `<name>.module.php` required at build time, never at run time.
 *
 * A module's name is its folder's name, lower case with underscores between
 * words; its classes live in `App\<StudlyName>\`. `app`, `generated` and
 * `module` are reserved: the hand-written app's own namespace, the generated
 * wiring's, and the name that would make its generated classes `ModuleRoutes`
 * and the rest.
 */
final readonly class ModuleReader
{
    /**
     * A module name: lower case words joined by underscores.
     */
    private const string NAME = '/^[a-z][a-z0-9]*(_[a-z0-9]+)*$/';

    /**
     * Names no module may take.
     */
    private const array RESERVED = ['app', 'generated', 'module'];

    /** @param ManifestDeclarations $manifestDeclarations Rejects declarations with no build consumer. */
    public function __construct(private ManifestDeclarations $manifestDeclarations = new ManifestDeclarations())
    {
    }

    /**
     * @param string $root The repo root.
     *
     * @throws InvalidArgumentException When a folder has no manifest, or a manifest is not a valid module.
     *
     * @return list<ModuleDefinition> The modules, by name.
     */
    public function read(string $root): array
    {
        $directories = glob($root . '/modules/*', GLOB_ONLYDIR);
        $directories = $directories === false
            ? []
            : $directories;
        sort($directories);
        $modules = [];

        foreach ($directories as $directory) {
            $modules[] = $this->definition($directory);
        }

        return $modules;
    }

    /**
     * @param string $directory A module's folder.
     *
     * @throws InvalidArgumentException When it has no manifest, or its manifest is not a valid module.
     *
     * @return ModuleDefinition The module.
     */
    private function definition(string $directory): ModuleDefinition
    {
        $name = basename($directory);
        $file = $directory . '/' . $name . '.module.php';

        if (!is_file($file)) {
            throw new InvalidArgumentException('modules/' . $name . ': no manifest; add modules/' . $name . '/' . $name . '.module.php.');
        }

        $module = require $file;

        if (!$module instanceof ModuleManifest) {
            throw new InvalidArgumentException(
                'modules/' . $name . '/' . $name . '.module.php must return a ' . ModuleManifest::class . '.',
            );
        }

        $this->checkName($name, $module->name);
        $this->manifestDeclarations->check($module);

        return new ModuleDefinition($name, 'App\\' . str_replace('_', '', ucwords($name, '_')) . '\\', $module);
    }

    /**
     * @param string $folder   The module's folder name.
     * @param string $declared The name its manifest declares.
     *
     * @throws InvalidArgumentException When they differ, or the name is invalid or reserved.
     */
    private function checkName(string $folder, string $declared): void
    {
        if ($declared !== $folder) {
            throw new InvalidArgumentException(
                'module ' . $folder . ': name() returns "' . $declared . '"; it must be the folder\'s name.',
            );
        }

        if (preg_match(self::NAME, $folder) !== 1 || in_array($folder, self::RESERVED, true)) {
            throw new InvalidArgumentException(
                'module ' . $folder . ': a module name is lower case words joined by underscores, and not app, generated or module.',
            );
        }
    }
}
