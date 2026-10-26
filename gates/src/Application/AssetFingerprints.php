<?php

declare(strict_types=1);

namespace Gates\Application;

use AIC\Http\Contracts\PublicAsset;
use Gates\Domain\AssetCatalog;
use Gates\Domain\BuildPaths;
use InvalidArgumentException;
use JsonException;

use function array_key_exists;
use function array_map;
use function explode;
use function hash;
use function is_link;
use function json_encode;
use function strlen;
use function usort;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;

/** Publish one content-addressed asset set while preserving exact byte-identical compatibility paths. */
final readonly class AssetFingerprints
{
    /** @param string $root The project folder containing the build-owned public tree. */
    public function __construct(private string $root)
    {
    }

    /**
     * @param list<PublicAsset>     $assets  The logical representations before fingerprinting.
     * @param array<string, string> $outputs The verified publication bytes by absolute alias path.
     *
     * @throws InvalidArgumentException When identities overlap, metadata differs or a public output traverses a link.
     * @throws JsonException            When the deterministic fingerprint input cannot be encoded.
     *
     * @return AssetCatalog Canonical records and the complete canonical-plus-alias output set, without writing files.
     */
    public function plan(array $assets, array $outputs): AssetCatalog
    {
        usort($assets, static fn (PublicAsset $left, PublicAsset $right): int => $left->name <=> $right->name);
        $digest = $this->digest($assets);
        $canonical = [];

        foreach ($assets as $asset) {
            $contents = $this->contents($asset, $outputs);
            $published = $this->canonical($asset, $digest);
            $path = $this->root . '/' . BuildPaths::ASSETS . '/' . $published->file;
            $this->available($path, $published->file, $outputs);
            $outputs[$path] = $contents;
            $canonical[] = $published;
        }

        return new AssetCatalog($canonical, $outputs);
    }

    /**
     * @param list<PublicAsset> $assets The sorted complete logical output set.
     *
     * @throws JsonException When deterministic metadata cannot be encoded.
     *
     * @return string The full SHA-256 over stable logical identities and byte identities, independent of project root or mtime.
     */
    private function digest(array $assets): string
    {
        $records = array_map(static fn (PublicAsset $publicAsset): array => [
            'file' => $publicAsset->file,
            'mediaType' => $publicAsset->mediaType,
            'name' => $publicAsset->name,
            'sha256' => $publicAsset->sha256,
            'size' => $publicAsset->size,
        ], $assets);

        return hash('sha256', json_encode($records, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    /**
     * @param PublicAsset           $publicAsset The logical metadata before fingerprint publication.
     * @param array<string, string> $outputs     The verified publication bytes.
     *
     * @throws InvalidArgumentException When the logical representation is missing, already fingerprinted or inconsistent.
     *
     * @return string Its exact verified unchanged bytes.
     */
    private function contents(PublicAsset $publicAsset, array $outputs): string
    {
        $path = $this->root . '/' . BuildPaths::ASSETS . '/' . $publicAsset->file;

        if ($publicAsset->aliasPath !== $publicAsset->path || !array_key_exists($path, $outputs)) {
            throw new InvalidArgumentException($publicAsset->name . ': fingerprint inputs require one original publication path.');
        }

        $contents = $outputs[$path];

        if (strlen($contents) !== $publicAsset->size || hash('sha256', $contents) !== $publicAsset->sha256) {
            throw new InvalidArgumentException($publicAsset->name . ': fingerprint input bytes differ from their catalog metadata.');
        }

        return $contents;
    }

    /**
     * @param PublicAsset $publicAsset The original logical representation.
     * @param string      $digest      The complete set's deterministic SHA-256.
     *
     * @throws InvalidArgumentException When canonical metadata is invalid.
     *
     * @return PublicAsset Its canonical metadata with the original exact URL retained as an alias.
     */
    private function canonical(PublicAsset $publicAsset, string $digest): PublicAsset
    {
        $file = $digest . '/' . $publicAsset->file;

        return new PublicAsset(
            $publicAsset->name,
            '/assets/' . $file,
            $file,
            $publicAsset->mediaType,
            $publicAsset->sha256,
            $publicAsset->size,
            $publicAsset->path,
        );
    }

    /**
     * @param string                $path    The canonical output's absolute path.
     * @param string                $file    Its portable public-relative path.
     * @param array<string, string> $outputs The existing exact output paths.
     *
     * @throws InvalidArgumentException When canonical output overlaps an alias or traverses a symbolic link.
     */
    private function available(string $path, string $file, array $outputs): void
    {
        if (array_key_exists($path, $outputs)) {
            throw new InvalidArgumentException($file . ': canonical asset publication collides with another output.');
        }

        $selected = $this->root;

        foreach (explode('/', BuildPaths::ASSETS . '/' . $file) as $segment) {
            $selected .= '/' . $segment;

            if (is_link($selected)) {
                throw new InvalidArgumentException($file . ': canonical asset output cannot traverse a symbolic link.');
            }
        }
    }
}
