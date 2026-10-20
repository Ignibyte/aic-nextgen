<?php

declare(strict_types=1);

namespace AIC\Sapi\Contracts;

/**
 * One uploaded file as the SAPI gave it (#44): the form field, what the
 * client said about it, and where the SAPI put it. The client's name and type
 * are claims, never trusted; the path is the SAPI's own.
 */
final readonly class SapiUpload
{
    /**
     * @param string $field      The form field.
     * @param string $clientName The file name the client sent.
     * @param string $clientType The media type the client sent.
     * @param string $path       The SAPI's temporary file; empty when the upload failed.
     * @param int    $error      The `UPLOAD_ERR_*` code; 0 for success.
     * @param int    $size       Its size in bytes.
     */
    public function __construct(
        public string $field,
        public string $clientName,
        public string $clientType,
        public string $path,
        public int $error,
        public int $size,
    ) {
    }
}
