<?php

declare(strict_types=1);

namespace AIC\Controller\Application;

use AIC\Component\Application\Renderer;
use AIC\Component\Contracts\Layout;
use AIC\Component\Exception\UnknownComponent;
use AIC\Controller\Contracts\EventSource;
use AIC\Controller\Contracts\EventWriter;
use AIC\Controller\Contracts\ResponseWriter;
use AIC\Controller\Contracts\ServerEvent;
use AIC\Http\Application\FileResponses;
use AIC\Http\Application\ResponseBuilder;
use AIC\Tessera\Domain\TesseraRequest;
use InvalidArgumentException;
use JsonException;
use JsonSerializable;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

use function json_encode;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * Writes the kinds' response shapes as PSR-7 responses.
 */
final readonly class Psr7ResponseWriter implements EventWriter, ResponseWriter
{
    /**
     * @param ResponseBuilder $responseBuilder Makes the PSR-7 responses.
     * @param Renderer        $renderer        Renders a page's layout, the only reader of its markup (#98).
     * @param FileResponses   $fileResponses   The shared file and byte-range output.
     */
    public function __construct(
        private ResponseBuilder $responseBuilder,
        private Renderer $renderer,
        private FileResponses $fileResponses,
    ) {
    }

    /**
     * @param int    $status       The status.
     * @param Layout $layout       The page.
     * @param bool   $prefetchable Whether it answers `private, no-cache` (#254).
     *
     * @throws UnknownComponent         When no template renders one of its components.
     * @throws InvalidArgumentException When a constructed value or argument is invalid.
     *
     * @return ResponseInterface The HTML response, varied.
     */
    #[Override]
    public function document(int $status, Layout $layout, bool $prefetchable = false): ResponseInterface
    {
        $html = $this->renderer->document($layout);

        return $this->varied($this->cached(
            $this->responseBuilder->create($status, 'text/html; charset=utf-8', $html->markup()),
            $prefetchable,
        ));
    }

    /**
     * @param int                                                           $status        The page's status.
     * @param Layout                                                        $layout        The page.
     * @param list<string>                                                  $targets       The regions and component
     *                                                                                     paths Tessera asked for.
     * @param bool                                                          $removing      Whether a component path the
     *                                                                                     page no longer renders is
     *                                                                                     answered removed (#247).
     * @param list<array{scope: string, json: string, onlyIfMissing: bool}> $signalPatches The page's signal patches,
     *                                                                                     each JSON safe in a script
     *                                                                                     (#253).
     * @param bool                                                          $prefetchable  Whether it answers
     *                                                                                     `private, no-cache` (#254).
     *
     * @throws UnknownComponent         When no template renders one of its components.
     * @throws InvalidArgumentException When a constructed value or argument is invalid.
     *
     * @return ResponseInterface The response, `text/vnd.tessera+html` with `Tessera-Protocol: 0`, varied; the whole
     *                           document when the page has no such target and it is not removed.
     */
    #[Override]
    public function fragments(
        int $status,
        Layout $layout,
        array $targets,
        bool $removing = false,
        array $signalPatches = [],
        bool $prefetchable = false,
    ): ResponseInterface {
        $html = $this->renderer->fragments($layout, $targets, $removing, $signalPatches);

        if ($html === null) {
            return $this->document($status, $layout, $prefetchable);
        }

        return $this->varied($this->cached($this->responseBuilder->withHeader(
            $this->responseBuilder->create($status, 'text/vnd.tessera+html; charset=utf-8', $html->markup()),
            TesseraRequest::PROTOCOL,
            TesseraRequest::VERSION,
        ), $prefetchable));
    }

    /**
     * @throws InvalidArgumentException When a constructed value or argument is invalid.
     *
     * @return ResponseInterface A `204` with an empty body, varied, so no cache answers a plain request with it.
     */
    #[Override]
    public function unchanged(): ResponseInterface
    {
        return $this->varied($this->responseBuilder->create(204, 'text/plain; charset=utf-8', ''));
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
     * @param int    $status   The status.
     * @param string $document An `XmlDocument`, serialized (#233).
     *
     * @throws InvalidArgumentException When a response field is invalid.
     *
     * @return ResponseInterface The response, `application/xml; charset=utf-8`.
     */
    #[Override]
    public function xml(int $status, string $document): ResponseInterface
    {
        return $this->responseBuilder->create($status, 'application/xml; charset=utf-8', $document);
    }

    /**
     * @param string                 $path          The file to send.
     * @param string                 $mediaType     Its `Content-Type`.
     * @param string                 $downloadName  The name the browser saves it as.
     * @param ServerRequestInterface $serverRequest The request: its `Range` and conditions (#196).
     *
     * @throws InvalidArgumentException When a response field is invalid.
     * @throws RuntimeException         When a file is missing or its whole-file stream cannot be opened.
     *
     * @return ResponseInterface 200 with `Accept-Ranges`, 206 with the part and its `Content-Range`, 416, 304 or
     *                           412, each with the file's validators.
     */
    #[Override]
    public function download(
        string $path,
        string $mediaType,
        string $downloadName,
        ServerRequestInterface $serverRequest,
    ): ResponseInterface {
        return $this->fileResponses->download($path, $mediaType, $downloadName, $serverRequest);
    }

    /**
     * @param EventSource $eventSource       Gives the events.
     * @param bool        $pausedWhileHidden Whether the response asks Tessera to pause the stream while the page is
     *                                       hidden (#259).
     *
     * @throws InvalidArgumentException When a constructed value or argument is invalid.
     *
     * @return ResponseInterface The `text/event-stream` response, not cached and not buffered by a proxy
     *                           (`X-Accel-Buffering: no`, which nginx in front of php-fpm honours), with
     *                           `Tessera-Stream-Pause: hidden` when paused while hidden, its body pulling the events.
     */
    #[Override]
    public function events(EventSource $eventSource, bool $pausedWhileHidden = false): ResponseInterface
    {
        $eventStreamBody = new EventStreamBody($eventSource, $this);
        $response = $this->responseBuilder->createFromStream(200, 'text/event-stream; charset=utf-8', $eventStreamBody);
        $response = $this->responseBuilder->withHeader($response, 'Cache-Control', 'no-store');
        $response = $this->responseBuilder->withHeader($response, 'X-Accel-Buffering', 'no');

        if ($pausedWhileHidden) {
            $response = $this->responseBuilder->withHeader($response, TesseraRequest::STREAM_PAUSE, 'hidden');
        }

        return $this->varied($this->responseBuilder->withHeader($response, TesseraRequest::PROTOCOL, TesseraRequest::VERSION));
    }

    /**
     * @param ServerEvent $serverEvent The event the source sent.
     *
     * @throws UnknownComponent When no template renders the event's component.
     *
     * @return string The event on the wire, its element patch's component rendered by the one Renderer (#115).
     */
    #[Override]
    public function frame(ServerEvent $serverEvent): string
    {
        $component = $serverEvent->elements;

        if ($component === null) {
            return $serverEvent->wire('');
        }

        return $serverEvent->wire($this->renderer->elements($component)->markup());
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
     * @param ResponseInterface $response     A page's answer.
     * @param bool              $prefetchable Whether the page opted in (#254).
     *
     * @throws InvalidArgumentException When a constructed value or argument is invalid.
     *
     * @return ResponseInterface It, `private, no-cache` when the page is prefetchable, so Tessera may use a hover
     *                           prefetch of it; as it was, `private, no-store`, otherwise.
     */
    private function cached(ResponseInterface $response, bool $prefetchable): ResponseInterface
    {
        return $prefetchable
            ? $this->responseBuilder->withHeader($response, 'Cache-Control', 'private, no-cache')
            : $response;
    }
}
