<?php

declare(strict_types=1);

namespace Gates\Application;

use AIC\Http\Domain\AssetMediaTypes;
use AIC\Module\Contracts\ModuleAssetKind;
use InvalidArgumentException;

use function array_diff;
use function array_values;
use function explode;
use function file_exists;
use function is_dir;
use function is_file;
use function is_link;
use function preg_match;
use function realpath;
use function scandir;
use function sort;
use function str_starts_with;

/** Validates portable declared asset paths and audits owner folders without following symlinks. */
final readonly class ModuleAssets
{
    /** @param AssetMediaTypes $assetMediaTypes The shared build/runtime MIME map. */
    public function __construct(private AssetMediaTypes $assetMediaTypes = new AssetMediaTypes())
    {
    }

    /**
     * @param string          $root            The project folder.
     * @param string          $owner           The module name.
     * @param string          $source          Its owner-relative assets/ path.
     * @param ModuleAssetKind $moduleAssetKind The declared publication role.
     *
     * @throws InvalidArgumentException When the path, containment, file or role is invalid.
     *
     * @return string The trusted project-relative source.
     */
    public function source(string $root, string $owner, string $source, ModuleAssetKind $moduleAssetKind): string
    {
        $this->owner($root, $owner);

        if (!str_starts_with($source, 'assets/')) {
            throw new InvalidArgumentException('modules/' . $owner . '/' . $source . ': a declared asset starts with assets/.');
        }

        $folder = $root . '/modules/' . $owner;
        $this->segments($source);
        $this->contained($folder, $source);
        $this->role($source, $moduleAssetKind);

        return 'modules/' . $owner . '/' . $source;
    }

    /**
     * @param string $directory The owner assets directory.
     * @param string $relative  Its project-relative name for refusals.
     *
     * @throws InvalidArgumentException When an entry is unreadable, unsafe, symlinked or unsupported.
     *
     * @return list<string> Its regular files relative to that directory, sorted; none when absent.
     */
    public function files(string $directory, string $relative): array
    {
        if (is_link($directory)) {
            throw new InvalidArgumentException($relative . ': asset directories cannot be symlinks.');
        }

        if (file_exists($directory) && !is_dir($directory)) {
            throw new InvalidArgumentException($relative . ': the assets root must be a directory.');
        }

        return is_dir($directory)
            ? $this->under($directory, $relative, '')
            : [];
    }

    /**
     * @param string $file The source or publication name.
     *
     * @throws InvalidArgumentException When its extension is not a supported public asset type.
     *
     * @return string The explicit media type, never the generic download fallback.
     */
    public function mediaType(string $file): string
    {
        $mediaType = $this->assetMediaTypes->forFile($file);

        if ($mediaType === 'application/octet-stream') {
            throw new InvalidArgumentException($file . ': unsupported public asset media type.');
        }

        return $mediaType;
    }

    /**
     * @param string          $source          The declared source.
     * @param ModuleAssetKind $moduleAssetKind Its publication role.
     *
     * @throws InvalidArgumentException When its media type contradicts the role.
     */
    public function role(string $source, ModuleAssetKind $moduleAssetKind): void
    {
        $mediaType = $this->mediaType($source);
        $valid = match ($moduleAssetKind) {
            ModuleAssetKind::Style => $mediaType === 'text/css; charset=utf-8',
            ModuleAssetKind::Script => $mediaType === 'text/javascript; charset=utf-8',
            ModuleAssetKind::File => $mediaType !== 'text/css; charset=utf-8' && $mediaType !== 'text/javascript; charset=utf-8',
        };

        if (!$valid) {
            throw new InvalidArgumentException($source . ': media type does not match its declared asset role.');
        }
    }

    /**
     * @param string $root  The project folder.
     * @param string $owner The declared module identity.
     *
     * @throws InvalidArgumentException When the owner is not portable or traverses a symlink.
     */
    private function owner(string $root, string $owner): void
    {
        if (preg_match('/^[a-z][a-z0-9_]*$/D', $owner) !== 1) {
            throw new InvalidArgumentException($owner . ': invalid module owner for an asset.');
        }

        if (is_link($root . '/modules') || is_link($root . '/modules/' . $owner)) {
            throw new InvalidArgumentException('modules/' . $owner . ': asset ownership cannot pass through a symlink.');
        }
    }

    /**
     * @param string $path A portable relative path.
     *
     * @throws InvalidArgumentException When any segment is empty, dot-prefixed or nonportable.
     */
    private function segments(string $path): void
    {
        foreach (explode('/', $path) as $segment) {
            if (preg_match('/^[a-z0-9][a-z0-9._-]*$/D', $segment) !== 1) {
                throw new InvalidArgumentException($path . ': asset paths use portable lowercase, non-dot segments.');
            }
        }
    }

    /**
     * @param string $folder The owner folder.
     * @param string $source Its validated relative path.
     *
     * @throws InvalidArgumentException When it is not a contained regular file or traverses a symlink.
     */
    private function contained(string $folder, string $source): void
    {
        $path = $this->unlinked($folder, $source);
        $base = realpath($folder);
        $resolved = realpath($path);

        if ($base === false || $resolved === false || !str_starts_with($resolved, $base . '/') || !is_file($path)) {
            throw new InvalidArgumentException($path . ': declared asset is not a regular file inside its owner.');
        }
    }

    /**
     * @param string $folder The owning folder.
     * @param string $source Its portable relative source.
     *
     * @throws InvalidArgumentException When a source segment is a symlink.
     *
     * @return string The source path after every segment is checked.
     */
    private function unlinked(string $folder, string $source): string
    {
        $path = $folder;

        foreach (explode('/', $source) as $segment) {
            $path .= '/' . $segment;

            if (is_link($path)) {
                throw new InvalidArgumentException($path . ': declared assets cannot traverse symlinks.');
            }
        }

        return $path;
    }

    /**
     * @param string $directory The directory being audited.
     * @param string $relative  Its project-relative location.
     * @param string $prefix    Its path below the owner assets directory.
     *
     * @throws InvalidArgumentException When an entry cannot be read or validated.
     *
     * @return list<string> Its sorted descendant files.
     */
    private function under(string $directory, string $relative, string $prefix): array
    {
        $entries = scandir($directory);

        if ($entries === false) {
            throw new InvalidArgumentException($relative . ': cannot read the assets directory.');
        }

        $files = [];

        foreach (array_values(array_diff($entries, ['.', '..'])) as $entry) {
            $files = [...$files, ...$this->entry($directory, $relative, $prefix, $entry)];
        }

        sort($files);

        return $files;
    }

    /**
     * @param string $directory The audited directory.
     * @param string $relative  Its project-relative location.
     * @param string $prefix    Its path below the assets root.
     * @param string $entry     One directory entry.
     *
     * @throws InvalidArgumentException When it is not a safe directory or regular supported asset.
     *
     * @return list<string> This file or the directory's descendants.
     */
    private function entry(string $directory, string $relative, string $prefix, string $entry): array
    {
        $this->segments($entry);
        $path = $directory . '/' . $entry;

        if (is_link($path)) {
            throw new InvalidArgumentException($relative . '/' . $entry . ': asset symlinks are forbidden.');
        }

        if (is_dir($path)) {
            return $this->under($path, $relative . '/' . $entry, $prefix . $entry . '/');
        }

        $this->regular($path, $relative . '/' . $entry);

        return [$prefix . $entry];
    }

    /**
     * @param string $path     The audited file.
     * @param string $relative Its project-relative name.
     *
     * @throws InvalidArgumentException When it is not regular or has no supported media type.
     */
    private function regular(string $path, string $relative): void
    {
        if (!is_file($path)) {
            throw new InvalidArgumentException($relative . ': a public asset must be a regular file.');
        }

        $this->mediaType($relative);
    }
}
