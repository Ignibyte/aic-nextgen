<?php

declare(strict_types=1);

namespace Gates\Application;

use Gates\Domain\ModuleDefinition;
use InvalidArgumentException;

use function file_get_contents;
use function implode;
use function str_ends_with;

/**
 * Builds the public assets from the modules and the site theme (#39, #95).
 * One stylesheet, `public/assets/aic.css`: each module's `assets/*.css` by
 * name, the modules in requires order, then the site theme's `assets/*.css`
 * last, so the site's theme has the final word. Every other module file
 * (scripts, maps, images, fonts) is copied as it is to
 * `public/assets/<module>/<file>`; nothing links a script (Chad, 2026-10-21:
 * a component that needs one declares it, from #55). The theme folder holds
 * CSS only.
 */
final readonly class AssetBundle
{
    /**
     * The stylesheet's first line. It never quotes a glob such as `modules/<star>/assets`: a star
     * then a slash would end the comment.
     */
    private const string HEADER = "/* Built by bin/aic-build (#95) from each module's assets/ CSS, then the site theme's assets/ CSS: "
        . 'edit those and run composer run build, never this file. */';

    /**
     * @param string       $root         The repo root.
     * @param ModuleAssets $moduleAssets Lists and checks each assets folder.
     * @param BundledCss   $bundledCss   Checks and wraps each CSS file.
     */
    public function __construct(
        private string $root,
        private ModuleAssets $moduleAssets = new ModuleAssets(),
        private BundledCss $bundledCss = new BundledCss(),
    ) {
    }

    /**
     * @param list<ModuleDefinition> $modules The modules, in requires order.
     *
     * @throws InvalidArgumentException When an assets folder or a CSS file is refused, the theme has no CSS, or a file
     *                                  cannot be read.
     *
     * @return array<string, string> The stylesheet and each module's copies, by absolute path.
     */
    public function outputs(array $modules): array
    {
        $parts = [];
        $copies = [];

        foreach ($modules as $module) {
            [$moduleParts, $moduleCopies] = $this->module($module);
            $parts = [...$parts, ...$moduleParts];
            $copies = [...$copies, ...$moduleCopies];
        }

        $stylesheet = self::HEADER . "\n\n" . implode("\n", [...$parts, ...$this->theme()]);

        return [$this->root . '/public/assets/aic.css' => $stylesheet, ...$copies];
    }

    /**
     * @param ModuleDefinition $moduleDefinition A module.
     *
     * @throws InvalidArgumentException When its assets folder or a CSS file is refused, or a file cannot be read.
     *
     * @return array{list<string>, array<string, string>} Its stylesheet parts, and its copies by absolute path.
     */
    private function module(ModuleDefinition $moduleDefinition): array
    {
        $folder = 'modules/' . $moduleDefinition->name . '/assets';
        $public = $this->root . '/public/assets/' . $moduleDefinition->name . '/';
        $parts = [];
        $copies = [];

        foreach ($this->moduleAssets->files($this->root . '/' . $folder, $folder) as $file) {
            if (str_ends_with($file, '.css')) {
                $parts[] = $this->part($folder . '/' . $file);

                continue;
            }

            $copies[$public . $file] = $this->contents($folder . '/' . $file);
        }

        return [$parts, $copies];
    }

    /**
     * @throws InvalidArgumentException When the theme holds a file that is not CSS, holds no CSS, or a part is refused.
     *
     * @return list<string> The site theme's parts, by file name.
     */
    private function theme(): array
    {
        $parts = [];

        foreach ($this->moduleAssets->files($this->root . '/assets', 'assets') as $file) {
            if (!str_ends_with($file, '.css')) {
                throw new InvalidArgumentException(
                    'assets/' . $file . ": the site theme holds CSS only; a module's assets/ holds the rest.",
                );
            }

            $parts[] = $this->part('assets/' . $file);
        }

        if ($parts === []) {
            throw new InvalidArgumentException('The site theme in assets/ has no CSS.');
        }

        return $parts;
    }

    /**
     * @param string $path A CSS file, relative to the repo root.
     *
     * @throws InvalidArgumentException When it is refused or cannot be read.
     *
     * @return string Its part of the stylesheet.
     */
    private function part(string $path): string
    {
        return $this->bundledCss->part($path, $this->contents($path));
    }

    /**
     * @param string $path A file, relative to the repo root.
     *
     * @throws InvalidArgumentException When it cannot be read.
     *
     * @return string Its bytes.
     */
    private function contents(string $path): string
    {
        $contents = file_get_contents($this->root . '/' . $path);

        if ($contents === false) {
            throw new InvalidArgumentException('Cannot read ' . $path . '.');
        }

        return $contents;
    }
}
