<?php

declare(strict_types=1);

namespace Gates\Application;

use AIC\Module\Contracts\ModuleAssetKind;
use Gates\Domain\AssetInput;
use Gates\Domain\ThemeBinding;
use InvalidArgumentException;

use function array_diff;
use function array_key_exists;
use function array_map;
use function array_unique;
use function count;
use function implode;
use function preg_match;
use function str_starts_with;
use function strlen;
use function substr;

/** Feed explicit selected-theme assets into the same validated publication contract as module assets. */
final readonly class ThemeAssetInputs
{
    /**
     * @param string       $root         The project folder.
     * @param ModuleAssets $moduleAssets The shared public media, role and file checks.
     */
    public function __construct(private string $root, private ModuleAssets $moduleAssets = new ModuleAssets())
    {
    }

    /**
     * @param ThemeBinding|null $themeBinding The validated selected theme, absent when the app selects no theme.
     *
     * @throws InvalidArgumentException When an asset is missing, undeclared, repeated or unsupported.
     *
     * @return list<AssetInput> Its exact explicitly public inputs in declaration order.
     */
    public function read(?ThemeBinding $themeBinding): array
    {
        if ($themeBinding === null) {
            return [];
        }

        $inputs = [];
        $names = [];

        foreach ($themeBinding->moduleTheme->assets as $asset) {
            if (preg_match('/^[a-z][a-z0-9_-]*\\z/', $asset->name) !== 1 || array_key_exists($asset->name, $names)) {
                throw new InvalidArgumentException($asset->name . ': invalid or duplicate theme asset name.');
            }

            $names[$asset->name] = true;
            $inputs[] = $this->input($themeBinding->moduleTheme->name, $asset->name, $asset->source, $asset->moduleAssetKind);
        }

        $this->declared($themeBinding->moduleTheme->name, $inputs);

        return $inputs;
    }

    /**
     * @param string          $theme           The selected theme's portable name.
     * @param string          $name            The asset's local identity.
     * @param string          $source          Its explicit owner-relative source.
     * @param ModuleAssetKind $moduleAssetKind Its declared public role.
     *
     * @throws InvalidArgumentException When a path, media type or role is invalid.
     *
     * @return AssetInput The catalog input using the existing publication policy.
     */
    private function input(string $theme, string $name, string $source, ModuleAssetKind $moduleAssetKind): AssetInput
    {
        if (!str_starts_with($source, 'assets/')) {
            throw new InvalidArgumentException($source . ': a declared theme asset starts with assets/.');
        }

        $file = (new DeclaredPaths($this->root))->file('themes/' . $theme, $source, '');
        $this->moduleAssets->role($source, $moduleAssetKind);

        return new AssetInput('theme_' . $theme . '/' . $name, $file, 'themes/' . $theme . '/' . substr($source, 7), $moduleAssetKind);
    }

    /**
     * @param string           $theme  The selected theme.
     * @param list<AssetInput> $inputs Its validated public declarations.
     *
     * @throws InvalidArgumentException When an authored theme asset is unclaimed or repeated.
     */
    private function declared(string $theme, array $inputs): void
    {
        $folder = 'themes/' . $theme . '/assets';
        $files = $this->moduleAssets->files($this->root . '/' . $folder, $folder);
        $sources = array_map(
            static fn (AssetInput $assetInput): string => substr($assetInput->file, strlen('themes/' . $theme . '/')),
            $inputs,
        );
        $undeclared = array_diff($files, $sources);

        if ($undeclared !== [] || count(array_unique($sources)) !== count($sources)) {
            throw new InvalidArgumentException($folder . ': undeclared or repeated theme assets: ' . implode(', ', $undeclared) . '.');
        }
    }
}
