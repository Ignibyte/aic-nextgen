<?php

declare(strict_types=1);

namespace App\Account\Service;

use AIC\Controller\Domain\FileDownload;
use AIC\Form\Domain\CheckedUpload;
use AIC\Storage\Application\PrivateFiles;
use AIC\Storage\Application\PublicFiles;
use InvalidArgumentException;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;

/**
 * The last accepted upload, kept in both of the app's stores (#213): in the
 * public files as `uploads/latest.<extension>`, which the framework serves at
 * `/files/`, and in the private files as `uploads/latest.bin`, which only
 * `GET /uploads/latest` sends.
 */
final readonly class KeptUploads
{
    /**
     * The private copy's key.
     */
    private const string PRIVATE_KEY = 'uploads/latest.bin';

    /**
     * @param PublicFiles  $publicFiles  The public files.
     * @param PrivateFiles $privateFiles The private files.
     */
    public function __construct(
        private PublicFiles $publicFiles,
        private PrivateFiles $privateFiles,
    ) {
    }

    /**
     * @param UploadedFileInterface $uploadedFile  A checked upload.
     * @param CheckedUpload         $checkedUpload What the check found it to be.
     *
     * @throws InvalidArgumentException When a key is malformed.
     * @throws RuntimeException         When the upload cannot be read or a copy cannot be written.
     *
     * @return string The public copy's URL path.
     */
    public function keep(UploadedFileInterface $uploadedFile, CheckedUpload $checkedUpload): string
    {
        $this->privateFiles->putUpload(self::PRIVATE_KEY, $uploadedFile);

        return $this->publicFiles->putUpload('uploads/latest.' . $checkedUpload->extension, $uploadedFile);
    }

    /**
     * @param ServerRequestInterface $serverRequest The request: its `Range` and conditions.
     *
     * @throws InvalidArgumentException When the key is malformed.
     *
     * @return FileDownload The private copy as `latest.bin`; 404 before the first upload.
     */
    public function download(ServerRequestInterface $serverRequest): FileDownload
    {
        return $this->privateFiles->download(self::PRIVATE_KEY, 'application/octet-stream', 'latest.bin', $serverRequest);
    }
}
