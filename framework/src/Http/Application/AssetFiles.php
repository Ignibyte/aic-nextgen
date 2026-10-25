<?php

declare(strict_types=1);

namespace AIC\Http\Application;

use AIC\Http\Contracts\PublicAsset;
use AIC\Http\Domain\AssetFileInfo;

use function array_key_exists;
use function clearstatcache;
use function explode;
use function fclose;
use function fopen;
use function fread;
use function fstat;
use function hash;
use function is_array;
use function is_file;
use function is_link;
use function is_readable;
use function realpath;
use function str_starts_with;
use function stream_get_contents;
use function strlen;

use const PHP_INT_MAX;

/** Verify build-selected files without deriving filesystem paths from request text. */
final readonly class AssetFiles
{
    /** @param string $root The application root supplied by its composition root. */
    public function __construct(private string $root)
    {
    }

    /**
     * @param PublicAsset $publicAsset Trusted metadata from the generated catalog.
     *
     * @return AssetFileInfo|null Its verified byte snapshot, or null when unavailable or altered.
     */
    public function verified(PublicAsset $publicAsset): ?AssetFileInfo
    {
        clearstatcache(true);
        $path = $this->path($publicAsset);

        if ($path === null || !is_readable($path)) {
            return null;
        }

        return $this->matching($publicAsset, $path);
    }

    /**
     * @param PublicAsset $publicAsset The catalog's portable publication path.
     *
     * @return string|null Its canonical regular-file path beneath the public root, with no symlink segment.
     */
    private function path(PublicAsset $publicAsset): ?string
    {
        $base = $this->root . '/public/assets';

        if (is_link($this->root . '/public') || is_link($base)) {
            return null;
        }

        $canonical = realpath($base);

        return $canonical === false
            ? null
            : $this->beneath($canonical, $publicAsset->file);
    }

    /**
     * @param string $base The canonical public directory.
     * @param string $file A trusted catalog path relative to it.
     *
     * @return string|null Its regular file, or null when a segment is linked or the final path escapes.
     */
    private function beneath(string $base, string $file): ?string
    {
        $path = $base;

        foreach (explode('/', $file) as $segment) {
            $path .= '/' . $segment;

            if (is_link($path)) {
                return null;
            }
        }

        return $this->regular($path, $base);
    }

    /**
     * @param string $path The linked-segment-free selected path.
     * @param string $base The canonical public directory.
     *
     * @return string|null Its regular file beneath the public directory.
     */
    private function regular(string $path, string $base): ?string
    {
        $resolved = realpath($path);

        if ($resolved === false) {
            return null;
        }

        if (!str_starts_with($resolved, $base . '/') || !is_file($resolved)) {
            return null;
        }

        return $resolved;
    }

    /**
     * @param PublicAsset $publicAsset The expected byte identity from the build.
     * @param string      $path        The contained regular file.
     *
     * @return AssetFileInfo|null Its verified bytes and size, or null when unavailable or altered.
     */
    private function matching(PublicAsset $publicAsset, string $path): ?AssetFileInfo
    {
        if ($publicAsset->size < 0 || $publicAsset->size === PHP_INT_MAX) {
            return null;
        }

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return null;
        }

        try {
            return $this->snapshot($publicAsset, [$handle]);
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param PublicAsset              $publicAsset The expected byte identity.
     * @param non-empty-list<resource> $handles     The one freshly opened selected file.
     *
     * @return AssetFileInfo|null Its verified contents and modification time, or null on any failed observation.
     */
    private function snapshot(PublicAsset $publicAsset, array $handles): ?AssetFileInfo
    {
        $before = fstat($handles[0]);

        if (!is_array($before)) {
            return null;
        }

        $contents = $this->read($publicAsset, $handles);

        if ($contents === false) {
            return null;
        }

        $after = fstat($handles[0]);

        if (!is_array($after)) {
            return null;
        }

        if (!$this->unchanged($before, $after, $publicAsset->size)) {
            return null;
        }

        return $this->bytesMatch($publicAsset, $contents)
            ? new AssetFileInfo($contents, $after['mtime'])
            : null;
    }

    /**
     * @param array<int|string, int> $before The open handle's metadata before reading.
     * @param array<int|string, int> $after  The same handle's metadata after reading.
     * @param int                    $size   The exact catalog byte count.
     *
     * @return bool Whether both observations describe the same unchanged regular-file identity and expected size.
     */
    private function unchanged(array $before, array $after, int $size): bool
    {
        foreach (['dev', 'ino', 'mode', 'size', 'mtime', 'ctime'] as $key) {
            if (!$this->same($before, $after, $key)) {
                return false;
            }
        }

        return $this->regularMetadata($after, $size);
    }

    /**
     * @param array<int|string, int> $before The first handle observation.
     * @param array<int|string, int> $after  The second observation.
     * @param string                 $key    One required metadata field.
     *
     * @return bool Whether both observations include the same field value.
     */
    private function same(array $before, array $after, string $key): bool
    {
        return array_key_exists($key, $before) && array_key_exists($key, $after) && $before[$key] === $after[$key];
    }

    /**
     * @param PublicAsset $publicAsset The compiled byte identity.
     * @param string      $contents    The bounded bytes read from the selected handle.
     *
     * @return bool Whether the entire read matches the catalog's exact length and digest.
     */
    private function bytesMatch(PublicAsset $publicAsset, string $contents): bool
    {
        return strlen($contents) === $publicAsset->size && hash('sha256', $contents) === $publicAsset->sha256;
    }

    /**
     * @param PublicAsset              $publicAsset The expected representation length, checked before opening.
     * @param non-empty-list<resource> $handles     The same freshly opened file.
     *
     * @return string|false At most the expected bytes, or false on a failed read or extra lookahead byte.
     */
    private function read(PublicAsset $publicAsset, array $handles): string|false
    {
        $contents = stream_get_contents($handles[0], $publicAsset->size);

        if ($contents === false) {
            return false;
        }

        return fread($handles[0], 1) === ''
            ? $contents
            : false;
    }

    /**
     * @param array<int|string, int> $after The final descriptor metadata.
     * @param int                    $size  The expected byte count.
     *
     * @return bool Whether required metadata identifies a regular file with the exact expected size.
     */
    private function regularMetadata(array $after, int $size): bool
    {
        return array_key_exists('mode', $after) && array_key_exists('size', $after)
            && ($after['mode'] & 0o170000) === 0o100000 && $after['size'] === $size;
    }
}
