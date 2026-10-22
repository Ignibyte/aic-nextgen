<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use AIC\Workflow\Application\Make\ModuleFolders;
use Override;

use function basename;
use function glob;
use function is_file;
use function sort;

use const GLOB_ONLYDIR;

/**
 * The modules on disk (#122): each folder under `modules/` holding its
 * `<name>.module.php`, as the build reads them.
 */
final readonly class LocalModuleFolders implements ModuleFolders
{
    /**
     * @param string $root The project's root.
     */
    public function __construct(
        private string $root,
    ) {
    }

    /**
     * @return list<string> The modules' names, sorted.
     */
    #[Override]
    public function names(): array
    {
        $folders = glob($this->root . '/modules/*', GLOB_ONLYDIR);
        $names = [];

        foreach ($folders === false ? [] : $folders as $folder) {
            if (!is_file($folder . '/' . basename($folder) . '.module.php')) {
                continue;
            }

            $names[] = basename($folder);
        }

        sort($names);

        return $names;
    }
}
