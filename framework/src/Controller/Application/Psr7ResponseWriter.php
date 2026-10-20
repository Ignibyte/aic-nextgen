<?php

declare(strict_types=1);

namespace AIC\Controller\Application;

use AIC\Controller\Contracts\EventSource;
use AIC\Controller\Contracts\ResponseWriter;
use AIC\Http\Application\ResponseBuilder;
use AIC\Http\Domain\ByteRange;
use AIC\Http\Domain\RangeHeader;
use AIC\Http\Domain\RangeKind;
use AIC\Routing\Domain\RouteNotFoundException;
use JsonException;
use JsonSerializable;
use Override;
use Psr\Http\Message\ResponseInterface;

use function file_get_contents;
use function filesize;
use function is_file;
use function json_encode;
use function max;
use function str_replace;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * Writes the kinds' response shapes as PSR-7 responses.
 */
final readonly class Psr7ResponseWriter implements ResponseWriter
{
    /**
     * @param ResponseBuilder $responseBuilder Makes the PSR-7 responses.
     */
    public function __construct(
        private ResponseBuilder $responseBuilder,
    ) {
    }

    /**
     * @param int    $status The status.
     * @param string $html   The whole HTML document.
     *
     * @return ResponseInterface The HTML response.
     */
    #[Override]
    public function document(int $status, string $html): ResponseInterface
    {
        return $this->responseBuilder->create($status, 'text/html; charset=utf-8', $html);
    }

    /**
     * @param int    $status   A redirect status.
     * @param string $location Where to go.
     *
     * @return ResponseInterface The redirect, with an empty body.
     */
    #[Override]
    public function redirect(int $status, string $location): ResponseInterface
    {
        return $this->responseBuilder->withHeader(
            $this->responseBuilder->create($status, 'text/plain; charset=utf-8', ''),
            'Location',
            $location,
        );
    }

    /**
     * @param int              $status           The status.
     * @param JsonSerializable $jsonSerializable The body to encode.
     *
     * @throws JsonException When the body does not encode.
     *
     * @return ResponseInterface The JSON response.
     */
    #[Override]
    public function json(int $status, JsonSerializable $jsonSerializable): ResponseInterface
    {
        return $this->responseBuilder->create(
            $status,
            'application/json',
            json_encode($jsonSerializable, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        );
    }

    /**
     * @param string $path         The file to send.
     * @param string $mediaType    Its `Content-Type`.
     * @param string $downloadName The name the browser saves it as.
     * @param string $range        The request's `Range` header; empty for none.
     *
     * @throws RouteNotFoundException When there is no such file.
     *
     * @return ResponseInterface 200 with `Accept-Ranges`, 206 with the part and its `Content-Range`, or 416.
     */
    #[Override]
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
     * @param EventSource $eventSource Gives the events.
     *
     * @return ResponseInterface The `text/event-stream` response, not cached and not buffered by a proxy
     *                           (`X-Accel-Buffering: no`, which nginx in front of php-fpm honours), its body
     *                           pulling the events.
     */
    #[Override]
    public function events(EventSource $eventSource): ResponseInterface
    {
        $response = $this->responseBuilder->createFromStream(200, 'text/event-stream', new EventStreamBody($eventSource));

        return $this->responseBuilder->withHeader(
            $this->responseBuilder->withHeader($response, 'Cache-Control', 'no-cache'),
            'X-Accel-Buffering',
            'no',
        );
    }

    /**
     * @param string    $path         The file.
     * @param string    $mediaType    Its `Content-Type`.
     * @param string    $downloadName The name the browser saves it as.
     * @param ByteRange $byteRange    The part to send.
     *
     * @return ResponseInterface The 206 response with the part.
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
     * @param ResponseInterface $response     A file's response.
     * @param string            $downloadName The name the browser saves it as.
     *
     * @return ResponseInterface The response with `Content-Disposition` and `Accept-Ranges`.
     */
    private function attachment(ResponseInterface $response, string $downloadName): ResponseInterface
    {
        $name = str_replace('\\', '', str_replace('"', '', $downloadName));

        return $this->responseBuilder->withHeader(
            $this->responseBuilder->withHeader($response, 'Content-Disposition', 'attachment; filename="' . $name . '"'),
            'Accept-Ranges',
            'bytes',
        );
    }
}
