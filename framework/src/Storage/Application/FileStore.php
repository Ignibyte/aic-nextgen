<?php

declare(strict_types=1);

namespace AIC\Storage\Application;

use AIC\Storage\Domain\StorageKey;
use RuntimeException;

use function chmod;
use function dirname;
use function file_put_contents;
use function is_dir;
use function is_file;
use function mkdir;
use function rename;
use function tempnam;
use function unlink;

/**
 * One directory of stored files (#213), the work behind `PublicFiles` and
 * `PrivateFiles`. A write goes to a temporary file beside its target, named
 * with a leading dot so no key can name it, and is renamed over the target, so
 * a reader sees the old file or the new one, never half of one. elephc has no
 * `random_bytes()`, so `tempnam()` draws the temporary name.
 */
final readonly class FileStore
{
    /**
     * @param string $directory The store's directory, made when the first file is written.
     * @param int    $mode      The permissions each written file gets.
     */
    public function __construct(
        private string $directory,
        private int $mode,
    ) {
    }

    /**
     * @param StorageKey $storageKey The file.
     *
     * @return string Its path, whether or not it exists.
     */
    public function path(StorageKey $storageKey): string
    {
        return $this->directory . '/' . $storageKey->value;
    }

    /**
     * @param StorageKey $storageKey The file.
     * @param string     $contents   What it holds.
     *
     * @throws RuntimeException When its directory cannot be made or the file cannot be written.
     */
    public function put(StorageKey $storageKey, string $contents): void
    {
        $path = $this->path($storageKey);
        $directory = dirname($path);
        self::make($directory);
        $temporary = tempnam($directory, '.aic-');

        if ($temporary === false) {
            throw new RuntimeException('No temporary file can be made in ' . $directory . '.');
        }

        if (file_put_contents($temporary, $contents) === false || !chmod($temporary, $this->mode) || !rename($temporary, $path)) {
            unlink($temporary);

            throw new RuntimeException('The file ' . $storageKey->value . ' cannot be written.');
        }
    }

    /**
     * @param StorageKey $storageKey The file.
     *
     * @return bool Whether it exists.
     */
    public function exists(StorageKey $storageKey): bool
    {
        return is_file($this->path($storageKey));
    }

    /**
     * @param StorageKey $storageKey The file.
     *
     * @return bool Whether there was one to delete.
     */
    public function delete(StorageKey $storageKey): bool
    {
        $path = $this->path($storageKey);

        return is_file($path) && unlink($path);
    }

    /**
     * @param string $directory A directory.
     *
     * @throws RuntimeException When it is missing and cannot be made.
     */
    private static function make(string $directory): void
    {
        if (!is_dir($directory) && !mkdir($directory, 0o775, true) && !is_dir($directory)) {
            throw new RuntimeException('The directory ' . $directory . ' cannot be made.');
        }
    }
}
