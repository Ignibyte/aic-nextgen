<?php

declare(strict_types=1);

namespace Gates\Application;

use Gates\Domain\BuildPaths;

use function array_filter;
use function array_map;
use function array_values;
use function basename;
use function file_get_contents;
use function glob;
use function implode;
use function is_file;
use function sort;
use function str_replace;
use function ucwords;

use const GLOB_ONLYDIR;

/**
 * The root files an app takes from aic/gates (#130): each tool's stub, from the
 * package's `stubs/`, as the project should hold it. phpcs.xml.dist is the one
 * that varies: the stub, with one root namespace per module on disk and the
 * theme's two (#153), the lines the build prints, before the generated wiring's.
 */
final readonly class SuiteStubs
{
    /**
     * The stubs, by file name at the project's root.
     */
    public const array NAMES = [
        '.semgrepignore',
        'captainhook.json',
        'deptrac.yaml',
        'package-lock.json',
        'package.json',
        'phpcs.xml.dist',
        'phpstan-architecture.neon',
        'phpstan.neon',
        'pint.json',
        'psalm.xml',
        'rector.php',
    ];

    /**
     * The generated wiring's root, which every module's root goes before.
     */
    private const string GENERATED = '                <element key="' . BuildPaths::GENERATED . '" value="App\\Generated"/>';

    /**
     * @param string $package aic/gates' directory.
     */
    public function __construct(
        private string $package,
    ) {
    }

    /**
     * @param string $root The project's root.
     * @param string $name A stub's file name.
     *
     * @return string What the project's file should hold.
     */
    public function expected(string $root, string $name): string
    {
        $stub = (string) file_get_contents($this->package . '/stubs/' . $name);

        return $name === 'phpcs.xml.dist'
            ? str_replace(
                self::GENERATED,
                implode('', [...$this->moduleRoots($root), ...$this->themeRoots($root)]) . self::GENERATED,
                $stub,
            )
            : $stub;
    }

    /**
     * @param string $root The project's root.
     *
     * @return list<string> Each module's root namespace line, by module name, each with its line break.
     */
    private function moduleRoots(string $root): array
    {
        return array_map(
            static fn (string $name): string => '                <element key="modules/' . $name . '/src" value="App\\'
                . str_replace('_', '', ucwords($name, '_')) . '"/>' . "\n",
            $this->modules($root),
        );
    }

    /**
     * @param string $root The project's root.
     *
     * @return list<string> Each theme's two root namespace lines (#153), its components' and its layouts', each with
     *                      its line break.
     */
    private function themeRoots(string $root): array
    {
        $folders = glob($root . '/themes/*', GLOB_ONLYDIR);
        $names = array_map(basename(...), $folders === false ? [] : $folders);
        sort($names);
        $roots = [];

        foreach ($names as $name) {
            $roots[] = '                <element key="themes/' . $name . '/src/Components" value="App\\Theme\\Components"/>' . "\n";
            $roots[] = '                <element key="themes/' . $name . '/src/Layouts" value="App\\Theme\\Layouts"/>' . "\n";
        }

        return $roots;
    }

    /**
     * @param string $root The project's root.
     *
     * @return list<string> The modules on disk, each a folder with its manifest, sorted.
     */
    private function modules(string $root): array
    {
        $folders = glob($root . '/modules/*', GLOB_ONLYDIR);
        $names = array_values(array_filter(
            array_map(basename(...), $folders === false ? [] : $folders),
            static fn (string $name): bool => is_file($root . '/modules/' . $name . '/' . $name . '.module.php'),
        ));
        sort($names);

        return $names;
    }
}
