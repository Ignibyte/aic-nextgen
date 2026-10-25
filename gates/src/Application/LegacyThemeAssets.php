<?php

declare(strict_types=1);

namespace Gates\Application;

use InvalidArgumentException;

use function array_map;
use function file_get_contents;
use function str_contains;
use function str_ends_with;

/** The sole temporary root-assets CSS bridge; declared ModuleTheme input replaces it in ticket149. */
final readonly class LegacyThemeAssets
{
    /**
     * @param string       $root         The project folder.
     * @param ModuleAssets $moduleAssets Audits paths without following symlinks.
     * @param BundledCss   $bundledCss   Preserves the existing CSS grammar and output bytes.
     */
    public function __construct(
        private string $root,
        private ModuleAssets $moduleAssets = new ModuleAssets(),
        private BundledCss $bundledCss = new BundledCss(),
    ) {
    }

    /**
     * @throws InvalidArgumentException When the legacy theme is empty, unsafe, unreadable or holds non-CSS files.
     *
     * @return list<string> Legacy CSS parts in the original filename order.
     */
    public function parts(): array
    {
        $files = $this->moduleAssets->files($this->root . '/assets', 'assets');

        if ($files === []) {
            throw new InvalidArgumentException('The legacy site theme in assets/ has no CSS.');
        }

        return array_map($this->part(...), $files);
    }

    /**
     * @param string $file A validated legacy theme file below assets/.
     *
     * @throws InvalidArgumentException When it is not readable CSS or violates the bundling grammar.
     *
     * @return string Its unchanged bundle contribution.
     */
    private function part(string $file): string
    {
        if (!str_ends_with($file, '.css') || str_contains($file, '/')) {
            throw new InvalidArgumentException('assets/' . $file . ': the legacy root theme holds flat CSS only.');
        }

        $source = 'assets/' . $file;
        $contents = file_get_contents($this->root . '/' . $source);

        if ($contents === false) {
            throw new InvalidArgumentException($source . ': cannot read legacy theme CSS.');
        }

        return $this->bundledCss->part($source, $contents);
    }
}
