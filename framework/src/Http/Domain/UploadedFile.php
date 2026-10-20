<?php

declare(strict_types=1);

namespace AIC\Http\Domain;

use Override;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;

use function rename;

/**
 * One uploaded file (#44), over the temporary file the SAPI made. `moveTo`
 * renames it, which also moves it across filesystems. The path is the SAPI's
 * own, never the client's, so the move needs no upload check.
 */
final class UploadedFile implements UploadedFileInterface
{
    /**
     * Whether it has been moved; it can be moved once.
     */
    private bool $moved = false;

    /**
     * @param string          $path            The SAPI's temporary file; empty when the upload failed.
     * @param StreamInterface $stream          Its content.
     * @param int             $size            Its size in bytes.
     * @param int             $error           The `UPLOAD_ERR_*` code; 0 for success.
     * @param string          $clientFilename  The name the client sent; a claim.
     * @param string          $clientMediaType The media type the client sent; a claim.
     */
    public function __construct(
        private readonly string $path,
        private readonly StreamInterface $stream,
        private readonly int $size,
        private readonly int $error,
        private readonly string $clientFilename,
        private readonly string $clientMediaType,
    ) {
    }

    /**
     * @throws RuntimeException When it was moved, or did not upload.
     *
     * @return StreamInterface Its content.
     */
    #[Override]
    public function getStream(): StreamInterface
    {
        if ($this->moved || $this->error !== 0) {
            throw new RuntimeException('The uploaded file is not available: it was moved or did not upload.');
        }

        return $this->stream;
    }

    /**
     * @param string $targetPath Where to put it.
     *
     * @throws RuntimeException When it was moved already, did not upload, or cannot be moved.
     */
    #[Override]
    public function moveTo(string $targetPath): void
    {
        if ($this->moved || $this->error !== 0 || $this->path === '') {
            throw new RuntimeException('The uploaded file cannot be moved: it was moved already or did not upload.');
        }

        if (!rename($this->path, $targetPath)) {
            throw new RuntimeException('The uploaded file could not be moved to ' . $targetPath . '.');
        }

        $this->moved = true;
    }

    /**
     * @return int Its size in bytes.
     */
    #[Override]
    public function getSize(): int
    {
        return $this->size;
    }

    /**
     * @return int The `UPLOAD_ERR_*` code; 0 for success.
     */
    #[Override]
    public function getError(): int
    {
        return $this->error;
    }

    /**
     * @return string|null The name the client sent; null for none.
     */
    #[Override]
    public function getClientFilename(): ?string
    {
        return $this->clientFilename === ''
            ? null
            : $this->clientFilename;
    }

    /**
     * @return string|null The media type the client sent; null for none.
     */
    #[Override]
    public function getClientMediaType(): ?string
    {
        return $this->clientMediaType === ''
            ? null
            : $this->clientMediaType;
    }
}
