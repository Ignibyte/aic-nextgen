<?php

declare(strict_types=1);

namespace AIC\Http\Application;

use AIC\Http\Domain\ByteRange;
use AIC\Http\Domain\RangeHeader;
use AIC\Http\Domain\RangeKind;
use AIC\Routing\Domain\RouteNotFoundException;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

use function file_get_contents;
use function filesize;
use function is_file;
use function max;
use function str_replace;
use function strlen;
use function substr;

/** Shared file output: whole files, existing single-byte ranges and named downloads. */
final readonly class FileResponses
{
    /** @param ResponseBuilder $responseBuilder Makes the file responses and sets their headers. */
    public function __construct(private ResponseBuilder $responseBuilder)
    {
    }

    /**
     * @param string $path         The trusted file path.
     * @param string $mediaType    Its Content-Type.
     * @param string $downloadName Its attachment name, or empty for inline output.
     * @param string $range        The request's Range header, or empty for the whole file.
     *
     * @throws InvalidArgumentException When a response field is invalid.
     * @throws RuntimeException         When no regular file exists or the whole-file body cannot be opened.
     *
     * @return ResponseInterface The existing 200, 206 or 416 file response.
     */
    public function download(string $path, string $mediaType, string $downloadName, string $range): ResponseInterface
    {
        $size = is_file($path)
            ? filesize($path)
            : false;

        if ($size === false) {
            throw new RouteNotFoundException($path);
        }

        $byteRange = (new RangeHeader($range))->resolve($size);

        return match ($byteRange->kind) {
            RangeKind::Whole => $this->attachment($this->responseBuilder->createFromFile(200, $mediaType, $path), $downloadName),
            RangeKind::Part => $this->part($path, $mediaType, $downloadName, $byteRange),
            RangeKind::Unsatisfiable => $this->responseBuilder->withHeader(
                $this->responseBuilder->create(416, 'text/plain; charset=utf-8', ''),
                'Content-Range',
                $byteRange->contentRange(),
            ),
        };
    }

    /**
     * @param string $contents  The complete verified representation.
     * @param string $mediaType Its Content-Type.
     * @param string $range     The request's Range header, or empty for the whole representation.
     *
     * @throws InvalidArgumentException When a response field is invalid.
     * @throws RuntimeException         When a response body stream cannot be created.
     *
     * @return ResponseInterface Its inline 200, 206 or 416 response without any later file read.
     */
    public function bytes(string $contents, string $mediaType, string $range): ResponseInterface
    {
        $byteRange = (new RangeHeader($range))->resolve(strlen($contents));

        return match ($byteRange->kind) {
            RangeKind::Whole => $this->attachment($this->responseBuilder->create(200, $mediaType, $contents), ''),
            RangeKind::Part => $this->bytePart($contents, $mediaType, $byteRange),
            RangeKind::Unsatisfiable => $this->responseBuilder->withHeader(
                $this->responseBuilder->create(416, 'text/plain; charset=utf-8', ''),
                'Content-Range',
                $byteRange->contentRange(),
            ),
        };
    }

    /**
     * @param string    $contents  The complete verified representation.
     * @param string    $mediaType Its Content-Type.
     * @param ByteRange $byteRange The part to send.
     *
     * @throws InvalidArgumentException When a response field is invalid.
     * @throws RuntimeException         When a response body stream cannot be created.
     *
     * @return ResponseInterface The exact snapshot slice and its existing range headers.
     */
    private function bytePart(string $contents, string $mediaType, ByteRange $byteRange): ResponseInterface
    {
        $bytes = substr($contents, $byteRange->first, $byteRange->length());
        $response = $this->responseBuilder->create(206, $mediaType, $bytes);
        $ranged = $this->responseBuilder->withHeader($response, 'Content-Range', $byteRange->contentRange());

        return $this->attachment($ranged, '');
    }

    /**
     * @param string    $path         The file.
     * @param string    $mediaType    Its Content-Type.
     * @param string    $downloadName Its attachment name, or empty for inline output.
     * @param ByteRange $byteRange    The part to send.
     *
     * @throws InvalidArgumentException When a response field is invalid.
     *
     * @return ResponseInterface The 206 response with its existing range and attachment headers.
     */
    private function part(
        string $path,
        string $mediaType,
        string $downloadName,
        ByteRange $byteRange,
    ): ResponseInterface {
        $bytes = file_get_contents($path, false, null, $byteRange->first, max(0, $byteRange->length()));
        $response = $this->responseBuilder->create(206, $mediaType, $bytes === false ? '' : $bytes);

        return $this->attachment($this->responseBuilder->withHeader($response, 'Content-Range', $byteRange->contentRange()), $downloadName);
    }

    /**
     * @param ResponseInterface $response     A file response.
     * @param string            $downloadName Its attachment name, or empty for inline output.
     *
     * @throws InvalidArgumentException When a response field is invalid.
     *
     * @return ResponseInterface Its existing Accept-Ranges and optional Content-Disposition headers.
     */
    private function attachment(ResponseInterface $response, string $downloadName): ResponseInterface
    {
        $ranged = $this->responseBuilder->withHeader($response, 'Accept-Ranges', 'bytes');

        if ($downloadName === '') {
            return $ranged;
        }

        $name = str_replace('\\', '', str_replace('"', '', $downloadName));

        return $this->responseBuilder->withHeader($ranged, 'Content-Disposition', 'attachment; filename="' . $name . '"');
    }
}
