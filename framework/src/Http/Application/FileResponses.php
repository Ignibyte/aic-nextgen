<?php

declare(strict_types=1);

namespace AIC\Http\Application;

use AIC\Http\Domain\AssetConditions;
use AIC\Http\Domain\ByteRange;
use AIC\Http\Domain\EntityTags;
use AIC\Http\Domain\HttpDate;
use AIC\Http\Domain\RangeHeader;
use AIC\Http\Domain\RangeKind;
use AIC\Routing\Domain\RouteNotFoundException;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

use function dechex;
use function file_get_contents;
use function filemtime;
use function filesize;
use function hash;
use function is_file;
use function max;
use function str_replace;
use function strlen;
use function substr;
use function time;

/** Shared file output: whole files, existing single-byte ranges, named, validated downloads and tagged answers (#269). */
final readonly class FileResponses
{
    /**
     * @param ResponseBuilder $responseBuilder Makes the file responses and sets their headers.
     * @param AssetConditions $assetConditions Answers a download's conditions, as an asset's (#196).
     * @param HttpDate        $httpDate        Writes `Last-Modified`.
     * @param EntityTags      $entityTags      Matches an answer's `If-None-Match` (#269).
     */
    public function __construct(
        private ResponseBuilder $responseBuilder,
        private AssetConditions $assetConditions = new AssetConditions(),
        private HttpDate $httpDate = new HttpDate(),
        private EntityTags $entityTags = new EntityTags(),
    ) {
    }

    /**
     * An answer made in memory, validated by its body (#269): a 200 to a GET or HEAD gets the strong tag of the body's
     * SHA-256 and `private, no-cache`, so a client revalidates it on each use, and a request whose `If-None-Match`
     * matches that tag (the weak comparison, `*` included) gets 304 with no body and the same two headers. It has no
     * modification time, so `If-Modified-Since` is not read.
     *
     * @param ResponseInterface      $response      The answer, as written.
     * @param string                 $body          Its body.
     * @param ServerRequestInterface $serverRequest The request it answers.
     *
     * @throws InvalidArgumentException When a response field is invalid.
     *
     * @return ResponseInterface It tagged, or the 304; as it was for another status or method.
     */
    public function tagged(
        ResponseInterface $response,
        string $body,
        ServerRequestInterface $serverRequest,
    ): ResponseInterface {
        $method = $serverRequest->getMethod();

        if ($response->getStatusCode() !== 200 || ($method !== 'GET' && $method !== 'HEAD')) {
            return $response;
        }

        $etag = '"' . hash('sha256', $body) . '"';
        $answer = $this->entityTags->matches($serverRequest->getHeaderLine('If-None-Match'), $etag, true)
            ? $this->responseBuilder->create(304, $response->getHeaderLine('Content-Type'), '')
            : $response;
        $tagged = $this->responseBuilder->withHeader($answer, 'ETag', $etag);

        return $this->responseBuilder->withHeader($tagged, 'Cache-Control', 'private, no-cache');
    }

    /**
     * @param string                 $path          The trusted file path.
     * @param string                 $mediaType     Its Content-Type.
     * @param string                 $downloadName  Its attachment name, or empty for inline output.
     * @param ServerRequestInterface $serverRequest The request: its Range header and its conditions (#196).
     *
     * @throws InvalidArgumentException When a response field is invalid.
     * @throws RuntimeException         When no regular file exists or the whole-file body cannot be opened.
     *
     * @return ResponseInterface The 200, 206 or 416 file response, or 304 or 412 for a condition, with the
     *                           file's validators.
     */
    public function download(
        string $path,
        string $mediaType,
        string $downloadName,
        ServerRequestInterface $serverRequest,
    ): ResponseInterface {
        $size = is_file($path)
            ? filesize($path)
            : false;
        $modified = $size === false
            ? false
            : filemtime($path);

        if ($size === false || $modified === false) {
            throw new RouteNotFoundException($path);
        }

        $etag = '"' . dechex($modified) . '-' . dechex($size) . '"';
        $status = $this->assetConditions->status($serverRequest, $etag, $modified, time());
        $response = $status === 200
            ? $this->ranged($path, $mediaType, $downloadName, $this->assetConditions->range($serverRequest, $etag), $size)
            : $this->responseBuilder->create($status, 'text/plain; charset=utf-8', '');

        return $this->validated($response, $etag, $modified);
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
     * @param string $path         The trusted file path.
     * @param string $mediaType    Its Content-Type.
     * @param string $downloadName Its attachment name, or empty for inline output.
     * @param string $range        The Range header to honour, or empty for the whole file.
     * @param int    $size         The file's size.
     *
     * @throws InvalidArgumentException When a response field is invalid.
     * @throws RuntimeException         When the whole-file body cannot be opened.
     *
     * @return ResponseInterface The 200, 206 or 416 file response.
     */
    private function ranged(
        string $path,
        string $mediaType,
        string $downloadName,
        string $range,
        int $size,
    ): ResponseInterface {
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
     * @param ResponseInterface $response A download's response.
     * @param string            $etag     The file's strong entity tag.
     * @param int               $modified When the file last changed.
     *
     * @throws InvalidArgumentException When a response field is invalid.
     *
     * @return ResponseInterface It with the validators, revalidated on each use rather than never stored (#196).
     */
    private function validated(ResponseInterface $response, string $etag, int $modified): ResponseInterface
    {
        $tagged = $this->responseBuilder->withHeader($response, 'ETag', $etag);
        $dated = $this->responseBuilder->withHeader($tagged, 'Last-Modified', $this->httpDate->format($modified));

        return $this->responseBuilder->withHeader($dated, 'Cache-Control', 'private, no-cache');
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
