<?php

declare(strict_types=1);

namespace AIC\Controller\Domain;

use AIC\Controller\Contracts\ResponseWriter;
use AIC\Controller\Contracts\StreamResponse;
use Override;
use Psr\Http\Message\ResponseInterface;

/**
 * A stream answered with a file, sent whole as an attachment. Byte ranges
 * arrive with #25.
 */
final readonly class FileDownload implements StreamResponse
{
    /**
     * @param string $path         The file to send.
     * @param string $mediaType    Its `Content-Type`.
     * @param string $downloadName The name the browser saves it as.
     * @param string $range        The request's `Range` header, honoured as one byte range; empty for none.
     */
    public function __construct(
        private string $path,
        private string $mediaType,
        private string $downloadName,
        private string $range = '',
    ) {
    }

    /**
     * @param ResponseWriter $responseWriter Writes the response as PSR-7.
     *
     * @return ResponseInterface The response.
     */
    #[Override]
    public function writeTo(ResponseWriter $responseWriter): ResponseInterface
    {
        return $responseWriter->download($this->path, $this->mediaType, $this->downloadName, $this->range);
    }
}
