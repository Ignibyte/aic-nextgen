<?php

declare(strict_types=1);

namespace AIC\Storage\Application;

use AIC\Controller\Domain\FileDownload;
use AIC\Storage\Domain\StorageKey;
use InvalidArgumentException;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;

/**
 * The app's private files (#213), under `<root>/var/storage/private`: what
 * only someone a module allows may have, such as a member's export. They have
 * no URL; a module's own Stream checks who asks and answers `download()`. A
 * constructor of a page, block or job that asks for it gets it.
 *
 * @api
 */
final readonly class PrivateFiles
{
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
        $this->fileStore = new FileStore($root . '/var/storage/private', 0o600);
    }

    /**
     * @param string                 $key           A storage key (`exports/7.csv`).
     * @param string                 $mediaType     Its `Content-Type`.
     * @param string                 $downloadName  The name the browser saves it as; empty to send it inline.
     * @param ServerRequestInterface $serverRequest The request: its `Range` and conditions.
     *
     * @throws InvalidArgumentException When it is not a storage key.
     *
     * @return FileDownload The file, validated and ranged; 404 when it does not exist.
     */
    public function download(
        string $key,
        string $mediaType,
        string $downloadName,
        ServerRequestInterface $serverRequest,
    ): FileDownload {
        return new FileDownload($this->fileStore->path(new StorageKey($key)), $mediaType, $downloadName, $serverRequest);
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
     * @param string $key      A storage key.
     * @param string $contents What the file holds.
     *
     * @throws InvalidArgumentException When it is not a storage key.
     * @throws RuntimeException         When the file cannot be written.
     */
    public function put(string $key, string $contents): void
    {
        $this->fileStore->put(new StorageKey($key), $contents);
    }

    /**
     * Copies the upload's bytes: `moveTo()` is a rename, which fails between file systems.
     *
     * @param string                $key          A storage key.
     * @param UploadedFileInterface $uploadedFile An upload the caller has checked (`UploadCheck`).
     *
     * @throws InvalidArgumentException When it is not a storage key.
     * @throws RuntimeException         When the upload cannot be read or the file cannot be written.
     */
    public function putUpload(string $key, UploadedFileInterface $uploadedFile): void
    {
        $this->put($key, (string) $uploadedFile->getStream());
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
}
