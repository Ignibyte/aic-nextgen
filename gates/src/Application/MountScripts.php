<?php

declare(strict_types=1);

namespace Gates\Application;

use Gates\Domain\AssetKind;
use Gates\Domain\PresentationInventory;
use InvalidArgumentException;

use function array_diff;
use function array_slice;
use function array_values;
use function explode;
use function file_get_contents;
use function is_string;
use function str_starts_with;

/**
 * The build's check on component scripts (#315): a theme template that mounts a module script on an element
 * (Tessera's `data-mount`) names it by its public path, `/assets/themes/<theme>/<path>.js`, as a literal, and the
 * theme's `assets/` holds that script. Tessera imports it only on a page whose markup names it.
 */
final readonly class MountScripts
{
    /**
     * @param string           $root             The project folder.
     * @param ThemeAssetInputs $themeAssetInputs The theme's public assets.
     */
    public function __construct(
        private string $root,
        private ThemeAssetInputs $themeAssetInputs,
    ) {
    }

    /**
     * @param PresentationInventory $presentationInventory The theme and its templates.
     *
     * @throws InvalidArgumentException When a template's `data-mount` is not a literal naming one of the theme's scripts,
     *                                  or an asset cannot be read.
     */
    public function check(PresentationInventory $presentationInventory): void
    {
        $scripts = $this->scripts($presentationInventory);

        foreach ($presentationInventory->templates as $templateBinding) {
            $unknown = array_values(array_diff($this->mounted($templateBinding->source), $scripts));

            if ($unknown !== []) {
                throw new InvalidArgumentException(
                    $templateBinding->source . ': data-mount="' . $unknown[0] . '" names no script in the theme\'s assets/;'
                        . ' write the literal /assets/themes/<theme>/<path>.js of a .js file there (#315).',
                );
            }
        }
    }

    /**
     * @param PresentationInventory $presentationInventory The theme.
     *
     * @throws InvalidArgumentException When an asset is linked, nonportable or of an unsupported type.
     *
     * @return list<string> The public path of every script in the theme's `assets/`.
     */
    private function scripts(PresentationInventory $presentationInventory): array
    {
        $scripts = [];

        foreach ($this->themeAssetInputs->read($presentationInventory->theme) as $assetInput) {
            if ($assetInput->assetKind !== AssetKind::Script) {
                continue;
            }

            $scripts[] = '/assets/' . $assetInput->file;
        }

        return $scripts;
    }

    /**
     * @param string $source A template, relative to the project.
     *
     * @throws InvalidArgumentException When it cannot be read.
     *
     * @return list<string> The value of each `data-mount` attribute in it, as written; none outside a theme.
     */
    private function mounted(string $source): array
    {
        if (!str_starts_with($source, 'themes/')) {
            return [];
        }

        $text = file_get_contents($this->root . '/' . $source);

        if (!is_string($text)) {
            throw new InvalidArgumentException($source . ' cannot be read.');
        }

        $values = [];

        // gate:25's lint keeps one space before every attribute and its value in double quotes.
        foreach (array_slice(explode(' data-mount="', $text), 1) as $rest) {
            $values[] = explode('"', $rest, 2)[0];
        }

        return $values;
    }
}
