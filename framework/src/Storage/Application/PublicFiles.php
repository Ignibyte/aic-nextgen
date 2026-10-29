<?php

declare(strict_types=1);

namespace AIC\Storage\Application;

use AIC\Storage\Domain\StorageKey;
use InvalidArgumentException;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;

/**
 * The app's public files (#213), under `<root>/var/storage/public`: what any
 * visitor may fetch, such as a vehicle's photos. The framework serves each at
 * `/files/<key>` before any session or database work (`PublicFileHandler`). A
 * constructor of a page, block or job that asks for it gets it.
 *
 * @api
 */
final readonly class PublicFiles
{
    /**
     * Where the framework serves the files.
     */
    public const string PREFIX = '/files/';

    /**
     * The files.
     */
    private FileStore $fileStore;

    /**
     * @param string $root The app's root directory.
     */
    public function __construct(
        string $root,
    ) {
        $this->fileStore = new FileStore($root . '/var/storage/public', 0o644);
    }

    /**
     * @param string $key A storage key (`vehicles/12/3.jpg`).
     *
     * @throws InvalidArgumentException When it is not a storage key.
     *
     * @return string Its URL path, whether or not the file exists.
     */
    public function url(string $key): string
    {
        return self::PREFIX . (new StorageKey($key))->value;
    }

    /**
     * @param string $key      A storage key.
     * @param string $contents What the file holds.
     *
     * @throws InvalidArgumentException When it is not a storage key.
     * @throws RuntimeException         When the file cannot be written.
     *
     * @return string Its URL path.
     */
    public function put(string $key, string $contents): string
    {
        $this->fileStore->put(new StorageKey($key), $contents);

        return $this->url($key);
    }

    /**
     * Copies the upload's bytes: `moveTo()` is a rename, which fails between file systems.
     *
     * @param string                $key          A storage key.
     * @param UploadedFileInterface $uploadedFile An upload the caller has checked (`UploadCheck`).
     *
     * @throws InvalidArgumentException When it is not a storage key.
     * @throws RuntimeException         When the upload cannot be read or the file cannot be written.
     *
     * @return string Its URL path.
     */
    public function putUpload(string $key, UploadedFileInterface $uploadedFile): string
    {
        return $this->put($key, (string) $uploadedFile->getStream());
    }

    /**
     * @param string $key A storage key.
     *
     * @throws InvalidArgumentException When it is not a storage key.
     *
     * @return bool Whether the file exists.
     */
    public function exists(string $key): bool
    {
        return $this->fileStore->exists(new StorageKey($key));
    }

    /**
     * @param string $key A storage key.
     *
     * @throws InvalidArgumentException When it is not a storage key.
     *
     * @return bool Whether there was a file to delete.
     */
    public function delete(string $key): bool
    {
        return $this->fileStore->delete(new StorageKey($key));
    }

    /**
     * @param string $key What a `/files/` path names after the prefix, as sent.
     *
     * @return string|null The file's path when the key is well formed and the file exists; null otherwise.
     */
    public function served(string $key): ?string
    {
        try {
            $storageKey = new StorageKey($key);
        } catch (InvalidArgumentException) {
            return null;
        }

        return $this->fileStore->exists($storageKey)
            ? $this->fileStore->path($storageKey)
            : null;
    }
}
