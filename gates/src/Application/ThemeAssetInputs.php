<?php

declare(strict_types=1);

namespace Gates\Application;

use Gates\Domain\AssetInput;
use Gates\Domain\AssetKind;
use Gates\Domain\ThemeBinding;
use InvalidArgumentException;

use function str_ends_with;

/**
 * The theme's public assets (#153): every file under `themes/<theme>/assets/`, in path order, its role from its
 * extension. Nothing declares them; a script named `*.page.js` is one every page loads (#239).
 */
final readonly class ThemeAssetInputs
{
    /** The ending of a script every page loads (#239). */
    private const string PAGE = '.page.js';

    /**
     * @param string     $root       The project folder.
     * @param AssetFiles $assetFiles The shared public media, role and file checks.
     */
    public function __construct(private string $root, private AssetFiles $assetFiles = new AssetFiles())
    {
    }

    /**
     * @param ThemeBinding|null $themeBinding The configured theme, absent when the app names none.
     *
     * @throws InvalidArgumentException When an asset is linked, nonportable or of an unsupported type.
     *
     * @return list<AssetInput> Its public inputs, in path order.
     */
    public function read(?ThemeBinding $themeBinding): array
    {
        if ($themeBinding === null) {
            return [];
        }

        $theme = 'themes/' . $themeBinding->name;
        $inputs = [];

        foreach ($this->assetFiles->files($this->root . '/' . $theme . '/assets', $theme . '/assets') as $file) {
            $inputs[] = new AssetInput(
                'theme_' . $themeBinding->name . '/' . $file,
                $theme . '/assets/' . $file,
                $theme . '/' . $file,
                $this->assetFiles->kind($file),
            );
        }

        return $inputs;
    }

    /**
     * @param ThemeBinding|null $themeBinding The configured theme, absent when the app names none.
     *
     * @throws InvalidArgumentException When an asset is linked, nonportable or of an unsupported type.
     *
     * @return list<string> Its page scripts (#239): each `*.page.js` in its `assets/`, as its public path, in path
     *                      order. Every other script is published and selected by nothing.
     */
    public function pageScripts(?ThemeBinding $themeBinding): array
    {
        $scripts = [];

        foreach ($this->read($themeBinding) as $assetInput) {
            if ($assetInput->assetKind !== AssetKind::Script || !str_ends_with($assetInput->file, self::PAGE)) {
                continue;
            }

            $scripts[] = '/assets/' . $assetInput->file;
        }

        return $scripts;
    }
}
