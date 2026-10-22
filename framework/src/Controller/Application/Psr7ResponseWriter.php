<?php

declare(strict_types=1);

namespace AIC\Controller\Application;

use AIC\Component\Application\Renderer;
use AIC\Component\Contracts\Layout;
use AIC\Component\Exception\UnknownComponent;
use AIC\Controller\Contracts\EventSource;
use AIC\Controller\Contracts\ResponseWriter;
use AIC\Http\Application\ResponseBuilder;
use AIC\Http\Domain\ByteRange;
use AIC\Http\Domain\RangeHeader;
use AIC\Http\Domain\RangeKind;
use AIC\Routing\Domain\RouteNotFoundException;
use AIC\Tessera\Domain\TesseraRequest;
use InvalidArgumentException;
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
     * @param Renderer        $renderer        Renders a page's layout, the only reader of its markup (#98).
     */
    public function __construct(
        private ResponseBuilder $responseBuilder,
        private Renderer $renderer,
    ) {
    }

    /**
     * @param int    $status The status.
     * @param Layout $layout The page.
     *
     * @throws UnknownComponent         When no template renders one of its components.
     * @throws InvalidArgumentException When a constructed value or argument is invalid.
     *
     * @return ResponseInterface The HTML response, varied.
     */
    #[Override]
    public function document(int $status, Layout $layout): ResponseInterface
    {
        $html = $this->renderer->document($layout);

        return $this->varied($this->responseBuilder->create($status, 'text/html; charset=utf-8', $html->markup()));
    }

    /**
     * @param int          $status  The page's status.
     * @param Layout       $layout  The page.
     * @param list<string> $targets The regions and component paths Tessera asked for.
     *
     * @throws UnknownComponent         When no template renders one of its components.
     * @throws InvalidArgumentException When a constructed value or argument is invalid.
     *
     * @return ResponseInterface The response, `text/vnd.tessera+html` with `Tessera-Protocol: 0`, varied; the whole
     *                           document when the page has no such target.
     */
    #[Override]
    public function fragments(int $status, Layout $layout, array $targets): ResponseInterface
    {
        $html = $this->renderer->fragments($layout, $targets);

        if ($html === null) {
            return $this->document($status, $layout);
        }

        return $this->varied($this->responseBuilder->withHeader(
            $this->responseBuilder->create($status, 'text/vnd.tessera+html; charset=utf-8', $html->markup()),
            TesseraRequest::PROTOCOL,
            TesseraRequest::VERSION,
        ));
    }

    /**
     * @param int    $status   A redirect status.
     * @param string $location Where to go.
     *
     * @throws InvalidArgumentException When a constructed value or argument is invalid.
     *
     * @return ResponseInterface The redirect, with an empty body.
     */
    #[Override]
    public function redirect(int $status, string $location): ResponseInterface
    {
        return $this->varied($this->responseBuilder->withHeader(
            $this->responseBuilder->create($status, 'text/plain; charset=utf-8', ''),
            'Location',
            $location,
        ));
    }

    /**
     * @param int              $status           The status.
     * @param JsonSerializable $jsonSerializable The body to encode.
     *
     * @throws JsonException            When the body does not encode.
     * @throws InvalidArgumentException When a constructed value or argument is invalid.
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
     * @throws InvalidArgumentException When a constructed value or argument is invalid.
     *
     * @return ResponseInterface The `text/event-stream` response, not cached and not buffered by a proxy
     *                           (`X-Accel-Buffering: no`, which nginx in front of php-fpm honours), its body
     *                           pulling the events.
     */
    #[Override]
    public function events(EventSource $eventSource): ResponseInterface
    {
        $response = $this->responseBuilder->createFromStream(200, 'text/event-stream; charset=utf-8', new EventStreamBody($eventSource));
        $response = $this->responseBuilder->withHeader($response, 'Cache-Control', 'no-store');
        $response = $this->responseBuilder->withHeader($response, 'X-Accel-Buffering', 'no');

        return $this->varied($this->responseBuilder->withHeader($response, TesseraRequest::PROTOCOL, TesseraRequest::VERSION));
    }

    /**
     * @param string $location Where the browser loads in full.
     *
     * @throws InvalidArgumentException When a constructed value or argument is invalid.
     *
     * @return ResponseInterface A `303` to it carrying `Tessera-Full-Visit`, which the redirect middleware keeps for Tessera.
     */
    #[Override]
    public function fullVisit(string $location): ResponseInterface
    {
        return $this->responseBuilder->withHeader($this->redirect(303, $location), TesseraRequest::FULL_VISIT, $location);
    }

    /**
     * @param ResponseInterface $response A page's response.
     *
     * @throws InvalidArgumentException When a constructed value or argument is invalid.
     *
     * @return ResponseInterface It with `Vary` on Tessera's request headers (protocol 8), since the same URL answers both.
     */
    private function varied(ResponseInterface $response): ResponseInterface
    {
        return $this->responseBuilder->withHeader($response, 'Vary', TesseraRequest::VARY);
    }

    /**
     * @param string    $path         The file.
     * @param string    $mediaType    Its `Content-Type`.
     * @param string    $downloadName The name the browser saves it as.
     * @param ByteRange $byteRange    The part to send.
     *
     * @throws InvalidArgumentException When a constructed value or argument is invalid.
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
     * @param string            $downloadName The name the browser saves it as; empty to show it inline.
     *
     * @throws InvalidArgumentException When a constructed value or argument is invalid.
     *
     * @return ResponseInterface The response with `Accept-Ranges`, and `Content-Disposition` for a named download.
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
