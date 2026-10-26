<?php

declare(strict_types=1);

namespace Gates\Application;

use AIC\Workflow\Application\Make\ModuleFolders;
use AIC\Workflow\Application\ProjectFiles;
use Override;

use function array_any;
use function array_filter;
use function array_key_exists;
use function array_map;
use function array_values;
use function basename;
use function file_get_contents;
use function glob;
use function is_dir;
use function is_file;
use function is_link;
use function sort;
use function str_starts_with;

use const ARRAY_FILTER_USE_KEY;
use const GLOB_ONLYDIR;

/**
 * The project as gate:31 lets the make commands see it (#122): every file on
 * disk but `modules/scaffold/`, which starts empty, and every write kept in
 * memory, so the made module can be compared with the committed one and
 * nothing on disk changes.
 */
final class ScaffoldFiles implements ModuleFolders, ProjectFiles
{
    /**
     * The folder the overlay hides and holds in memory.
     */
    public const string FOLDER = 'modules/scaffold/';

    /**
     * The scaffold's components in the theme (#153), which the overlay hides too: every folder starting so.
     */
    public const string THEME = 'themes/app/components/Scaffold';

    /**
     * The scaffold's layout files in the theme (#157), which the overlay hides too: every file starting so.
     */
    public const array LAYOUTS = [
        'themes/app/layouts/Scaffold',
        'themes/app/layouts/page--scaffold',
        'themes/app/layouts/html--scaffold',
    ];

    /**
     * @var array<string, string> The files written, by path.
     */
    private array $written = [];

    /**
     * @param string $root The project's root.
     */
    public function __construct(
        private readonly string $root,
    ) {
    }

    /**
     * @param string $path A path.
     *
     * @return string|null Its bytes: written ones under the folder, the disk's elsewhere; null when there is none.
     */
    #[Override]
    public function read(string $path): ?string
    {
        if (self::hidden($path)) {
            return $this->written[$path] ?? null;
        }

        $absolute = $this->root . '/' . $path;

        return is_file($absolute) && !is_link($absolute)
            ? (string) file_get_contents($absolute)
            : null;
    }

    /**
     * @param string $path     A path.
     * @param string $contents Its bytes.
     */
    #[Override]
    public function write(string $path, string $contents): void
    {
        $this->written[$path] = $contents;
    }

    /**
     * @param string $path A path.
     */
    #[Override]
    public function remove(string $path): void
    {
        unset($this->written[$path]);
    }

    /**
     * @param string $path A path.
     *
     * @return string What keeps a regular file from being written there; nothing under the hidden folder.
     */
    #[Override]
    public function obstacle(string $path): string
    {
        $absolute = $this->root . '/' . $path;

        return !self::hidden($path . '/') && (is_link($absolute) || is_dir($absolute))
            ? $path . ' is a symlink or a directory'
            : '';
    }

    /**
     * @return list<string> The modules on disk but `scaffold`, and `scaffold` once its manifest is written.
     */
    #[Override]
    public function names(): array
    {
        $folders = glob($this->root . '/modules/*', GLOB_ONLYDIR);
        $names = array_values(array_filter(
            array_map(basename(...), $folders === false ? [] : $folders),
            fn (string $name): bool => $name !== 'scaffold' && is_file($this->root . '/modules/' . $name . '/' . $name . '.module.php'),
        ));

        if (array_key_exists(self::FOLDER . 'scaffold.module.php', $this->written)) {
            $names[] = 'scaffold';
        }

        sort($names);

        return $names;
    }

    /**
     * @return array<string, string> The files written under the hidden folders, by path.
     */
    public function made(): array
    {
        return array_filter($this->written, self::hidden(...), ARRAY_FILTER_USE_KEY);
    }

    /**
     * @param string $path A path.
     *
     * @return bool Whether the overlay hides it: under the module's folder or a scaffold component's, or a scaffold
     *              layout file.
     */
    private static function hidden(string $path): bool
    {
        return array_any(
            [self::FOLDER, self::THEME, ...self::LAYOUTS],
            static fn (string $prefix): bool => str_starts_with($path, $prefix),
        );
    }
}
