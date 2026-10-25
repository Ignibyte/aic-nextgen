<?php

declare(strict_types=1);

namespace Gates\Application;

use AIC\Http\Domain\AssetMediaTypes;
use Gates\Domain\AssetKind;
use InvalidArgumentException;

use function array_diff;
use function array_values;
use function explode;
use function file_exists;
use function is_dir;
use function is_file;
use function is_link;
use function preg_match;
use function scandir;
use function sort;

/** Audits the theme's asset folder without following symlinks, and gives each file its media type and role. */
final readonly class AssetFiles
{
    /** @param AssetMediaTypes $assetMediaTypes The shared build/runtime MIME map. */
    public function __construct(private AssetMediaTypes $assetMediaTypes = new AssetMediaTypes())
    {
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
     * @param string $file An asset's path.
     *
     * @throws InvalidArgumentException When its extension is not a supported public asset type.
     *
     * @return AssetKind Its role: a stylesheet, a script, or a file to copy.
     */
    public function kind(string $file): AssetKind
    {
        return match ($this->mediaType($file)) {
            'text/css; charset=utf-8' => AssetKind::Style,
            'text/javascript; charset=utf-8' => AssetKind::Script,
            default => AssetKind::File,
        };
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
