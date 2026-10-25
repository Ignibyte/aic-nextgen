<?php

declare(strict_types=1);

namespace Gates\Application;

use AIC\Http\Contracts\PublicAsset;
use Gates\Domain\AssetCatalog;
use Gates\Domain\AssetInput;
use Gates\Domain\ModuleDefinition;
use Gates\Domain\ThemeBinding;
use InvalidArgumentException;
use JsonException;

use function array_any;
use function array_key_exists;
use function array_map;
use function basename;
use function explode;
use function hash;
use function is_link;
use function json_encode;
use function preg_match;
use function str_starts_with;
use function strlen;
use function substr;
use function usort;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;

/** Plans deterministic declared publication and its private inventory without emitting any generated or public file. */
final readonly class AssetCatalogBuild
{
    /**
     * @param string       $root         The project folder.
     * @param ModuleAssets $moduleAssets Uses the shared validated MIME definitions.
     */
    public function __construct(private string $root, private ModuleAssets $moduleAssets = new ModuleAssets())
    {
    }

    /**
     * @param list<ModuleDefinition> $modules        The modules in dependency order.
     * @param array<string, string>  $tesseraOutputs Verified pinned release bytes by absolute public output path.
     * @param ThemeBinding|null      $themeBinding   The selected theme, absent for bounded legacy apps.
     *
     * @throws InvalidArgumentException When declarations, paths, roles or output identities collide or are invalid.
     * @throws JsonException            When inventory metadata cannot be encoded.
     *
     * @return AssetCatalog The complete non-writing output plan.
     */
    public function plan(array $modules, array $tesseraOutputs, ?ThemeBinding $themeBinding = null): AssetCatalog
    {
        $assetInputs = [
            ...(new DeclaredAssetInputs($this->root))->read($modules),
            ...(new ThemeAssetInputs($this->root))->read($themeBinding),
        ];
        $outputs = (new AssetBundle($this->root))->outputs($assetInputs);
        $assets = [$this->asset('app/styles', 'aic.css', $outputs[$this->root . '/public/assets/aic.css'])];

        foreach ($assetInputs as $assetInput) {
            $assets = $this->append($assets, $this->input($assetInput, $outputs));
        }

        foreach ($tesseraOutputs as $path => $contents) {
            $assets = $this->append($assets, $this->tessera($path, $contents));
            $outputs[$path] = $contents;
        }

        usort($assets, static fn (PublicAsset $left, PublicAsset $right): int => $left->name <=> $right->name);
        $assetCatalog = (new AssetFingerprints($this->root))->plan($assets, $outputs);
        $this->unlinkedOutput('build/assets.json');
        $outputs = $assetCatalog->outputs;
        $outputs[$this->root . '/build/assets.json'] = $this->inventory($assetCatalog->assets);

        return new AssetCatalog($assetCatalog->assets, $outputs);
    }

    /**
     * @param AssetInput            $assetInput A declared module input.
     * @param array<string, string> $outputs    The planned module output bytes.
     *
     * @throws InvalidArgumentException When its expected publication is absent or unsupported.
     *
     * @return PublicAsset Its immutable catalog metadata.
     */
    private function input(AssetInput $assetInput, array $outputs): PublicAsset
    {
        $path = $this->root . '/public/assets/' . $assetInput->file;

        if (!array_key_exists($path, $outputs)) {
            throw new InvalidArgumentException($assetInput->source . ': planned asset output is missing.');
        }

        return $this->asset($assetInput->name, $assetInput->file, $outputs[$path]);
    }

    /**
     * @param string $path     A verified release output path.
     * @param string $contents Its pinned unchanged bytes.
     *
     * @throws InvalidArgumentException When the output is not a canonical pinned release path.
     *
     * @return PublicAsset The release catalog entry.
     */
    private function tessera(string $path, string $contents): PublicAsset
    {
        $prefix = $this->root . '/public/assets/';

        if (!str_starts_with($path, $prefix)) {
            throw new InvalidArgumentException($path . ': Tessera output leaves public/assets/.');
        }

        $file = substr($path, strlen($prefix));

        if (preg_match('/^tessera-[0-9]+\\.[0-9]+\\.[0-9]+\\/[a-z0-9.]+$/D', $file) !== 1) {
            throw new InvalidArgumentException($path . ': Tessera output is not a pinned release file.');
        }

        return $this->asset('tessera/' . basename($file), $file, $contents);
    }

    /**
     * @param string $name     The logical identity.
     * @param string $file     Its canonical publication path below public/assets/.
     * @param string $contents The exact planned bytes.
     *
     * @throws InvalidArgumentException When its media type is unsupported.
     *
     * @return PublicAsset The content-bound catalog record.
     */
    private function asset(string $name, string $file, string $contents): PublicAsset
    {
        $this->unlinkedOutput('public/assets/' . $file);

        return new PublicAsset(
            $name,
            '/assets/' . $file,
            $file,
            $this->moduleAssets->mediaType($file),
            hash('sha256', $contents),
            strlen($contents),
            '/assets/' . $file,
        );
    }

    /**
     * @param string $relative The trusted generated output below the project folder.
     *
     * @throws InvalidArgumentException When an output path traverses a pre-existing symlink.
     */
    private function unlinkedOutput(string $relative): void
    {
        $path = $this->root;

        foreach (explode('/', $relative) as $segment) {
            $path .= '/' . $segment;

            if (is_link($path)) {
                throw new InvalidArgumentException($relative . ': planned asset outputs cannot traverse symlinks.');
            }
        }
    }

    /**
     * @param list<PublicAsset> $assets      Previously planned representations.
     * @param PublicAsset       $publicAsset The next representation.
     *
     * @throws InvalidArgumentException When logical identity or publication path collides.
     *
     * @return list<PublicAsset> The catalog with the new entry appended.
     */
    private function append(array $assets, PublicAsset $publicAsset): array
    {
        if (
            array_any($assets, static fn (PublicAsset $before): bool => $before->name === $publicAsset->name
                || $before->path === $publicAsset->path || $before->file === $publicAsset->file)
        ) {
            throw new InvalidArgumentException($publicAsset->name . ': duplicate logical identity or public asset path.');
        }

        return [...$assets, $publicAsset];
    }

    /**
     * @param list<PublicAsset> $assets The sorted catalog.
     *
     * @throws JsonException When metadata cannot be encoded.
     *
     * @return string The deterministic private inventory, without authored source paths.
     */
    private function inventory(array $assets): string
    {
        $records = array_map(static fn (PublicAsset $publicAsset): array => [
            'aliasPath' => $publicAsset->aliasPath,
            'file' => $publicAsset->file,
            'mediaType' => $publicAsset->mediaType,
            'name' => $publicAsset->name,
            'path' => $publicAsset->path,
            'sha256' => $publicAsset->sha256,
            'size' => $publicAsset->size,
        ], $assets);

        return json_encode(
            ['assets' => $records, 'schema' => 'aic.assets/1'],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        ) . "\n";
    }
}
