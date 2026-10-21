<?php

declare(strict_types=1);

namespace AIC\Controller\Contracts;

use JsonSerializable;
use Psr\Http\Message\ResponseInterface;

/**
 * Writes a kind's sealed response as PSR-7, one method per response shape.
 *
 * A shape writes itself through this contract (double dispatch), so no handler
 * casts a response down to its shape, and a new shape needs a method here: the
 * seal is in the types.
 */
interface ResponseWriter
{
    /**
     * @param int    $status The status.
     * @param string $html   The whole HTML document.
     *
     * @return ResponseInterface The response.
     */
    public function document(int $status, string $html): ResponseInterface;

    /**
     * @param int    $status The page's status.
     * @param string $body   Tessera fragments: the title, the tracked assets and one template per target (#70).
     *
     * @return ResponseInterface The response, `text/vnd.tessera+html` with `Tessera-Protocol: 0`.
     */
    public function fragments(int $status, string $body): ResponseInterface;

    /**
     * @param int    $status   A redirect status.
     * @param string $location Where to go.
     *
     * @return ResponseInterface The response.
     */
    public function redirect(int $status, string $location): ResponseInterface;

    /**
     * @param int              $status           The status.
     * @param JsonSerializable $jsonSerializable The body to encode.
     *
     * @return ResponseInterface The response.
     */
    public function json(int $status, JsonSerializable $jsonSerializable): ResponseInterface;

    /**
     * @param string $path         The file to send.
     * @param string $mediaType    Its `Content-Type`.
     * @param string $downloadName The name the browser saves it as.
     * @param string $range        The request's `Range` header; empty for none.
     *
     * @return ResponseInterface The response: 200, 206 for a range, or 416 for one the file cannot satisfy.
     */
    public function download(string $path, string $mediaType, string $downloadName, string $range): ResponseInterface;

    /**
     * @param EventSource $eventSource Gives the events.
     *
     * @return ResponseInterface The `text/event-stream` response, its body pulling the events as it is read.
     */
    public function events(EventSource $eventSource): ResponseInterface;
}
