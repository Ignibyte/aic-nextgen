<?php

declare(strict_types=1);

namespace Gates\Application;

use AIC\Module\Contracts\ModuleAsset;
use Gates\Domain\AssetInput;
use Gates\Domain\ModuleDefinition;
use InvalidArgumentException;

use function array_any;
use function array_diff;
use function array_map;
use function implode;
use function preg_match;
use function strlen;
use function substr;

/** Resolves only explicit module asset declarations and rejects every undeclared authored asset file. */
final readonly class DeclaredAssetInputs
{
    /**
     * @param string       $root         The project folder.
     * @param ModuleAssets $moduleAssets Validates portable owner-relative files and roles.
     */
    public function __construct(private string $root, private ModuleAssets $moduleAssets = new ModuleAssets())
    {
    }

    /**
     * @param list<ModuleDefinition> $moduleDefinitions The modules in dependency order.
     *
     * @throws InvalidArgumentException When a declaration, source or undeclared file is invalid.
     *
     * @return list<AssetInput> Inputs in module and declaration order.
     */
    public function read(array $moduleDefinitions): array
    {
        $inputs = [];

        foreach ($moduleDefinitions as $moduleDefinition) {
            $inputs = [...$inputs, ...$this->module($moduleDefinition)];
        }

        return $inputs;
    }

    /**
     * @param ModuleDefinition $moduleDefinition The owning module.
     *
     * @throws InvalidArgumentException When declarations collide or files are undeclared.
     *
     * @return list<AssetInput> Its validated declared inputs.
     */
    private function module(ModuleDefinition $moduleDefinition): array
    {
        $inputs = [];

        foreach ($moduleDefinition->declared(ModuleAsset::class) as $moduleAsset) {
            $assetInput = $this->input($moduleDefinition->name, $moduleAsset);
            $this->unique($inputs, $assetInput);
            $inputs[] = $assetInput;
        }

        $this->declared($moduleDefinition->name, $inputs);

        return $inputs;
    }

    /**
     * @param string      $owner       The owning module name.
     * @param ModuleAsset $moduleAsset Its explicit declaration.
     *
     * @throws InvalidArgumentException When the local identity, path or role is invalid.
     *
     * @return AssetInput The owner-qualified trusted input.
     */
    private function input(string $owner, ModuleAsset $moduleAsset): AssetInput
    {
        if (preg_match('/^[a-z][a-z0-9_-]*$/D', $moduleAsset->name) !== 1) {
            throw new InvalidArgumentException('modules/' . $owner . ': invalid asset name ' . $moduleAsset->name . '.');
        }

        $source = $this->moduleAssets->source($this->root, $owner, $moduleAsset->source, $moduleAsset->moduleAssetKind);

        return new AssetInput(
            $owner . '/' . $moduleAsset->name,
            $source,
            $owner . '/' . substr($moduleAsset->source, 7),
            $moduleAsset->moduleAssetKind,
        );
    }

    /**
     * @param list<AssetInput> $inputs     Its earlier declarations.
     * @param AssetInput       $assetInput The new declaration.
     *
     * @throws InvalidArgumentException When an identifier, source or publication path repeats.
     */
    private function unique(array $inputs, AssetInput $assetInput): void
    {
        if (
            array_any($inputs, static fn (AssetInput $before): bool => $before->name === $assetInput->name
                || $before->source === $assetInput->source || $before->file === $assetInput->file)
        ) {
            throw new InvalidArgumentException($assetInput->source . ': duplicate asset name, source or publication path.');
        }
    }

    /**
     * @param string           $owner  The owning module.
     * @param list<AssetInput> $inputs Its declared inputs.
     *
     * @throws InvalidArgumentException When any authored asset file lacks a declaration.
     */
    private function declared(string $owner, array $inputs): void
    {
        $folder = 'modules/' . $owner . '/assets';
        $files = $this->moduleAssets->files($this->root . '/' . $folder, $folder);
        $sources = array_map(static fn (AssetInput $assetInput): string => substr($assetInput->source, strlen($folder) + 1), $inputs);
        $undeclared = array_diff($files, $sources);

        if ($undeclared !== []) {
            throw new InvalidArgumentException($folder . ': undeclared asset files: ' . implode(', ', $undeclared) . '.');
        }
    }
}
