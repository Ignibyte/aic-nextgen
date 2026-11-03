<?php

declare(strict_types=1);

namespace AIC\Controller\Contracts;

use AIC\Component\Contracts\Layout;
use JsonSerializable;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

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
     * @param Layout $layout The page, rendered as the whole HTML document (#98).
     *
     * @return ResponseInterface The response.
     */
    public function document(int $status, Layout $layout): ResponseInterface;

    /**
     * @param int          $status   The page's status.
     * @param Layout       $layout   The page.
     * @param list<string> $targets  The regions and component paths Tessera asked for, in order (#70).
     * @param bool         $removing Whether a component path the page no longer renders is answered with mode
     *                               `remove` (a refresh, a submission or an action, #247) rather than the whole document.
     *
     * @return ResponseInterface Tessera fragments, `text/vnd.tessera+html` with `Tessera-Protocol: 0`: the title, the
     *                           tracked assets and one template per target; the whole document when one is missing
     *                           and not removed.
     */
    public function fragments(int $status, Layout $layout, array $targets, bool $removing = false): ResponseInterface;

    /**
     * @param string $location Where the browser loads in full.
     *
     * @return ResponseInterface A `303` that also carries `Tessera-Full-Visit` (#71).
     */
    public function fullVisit(string $location): ResponseInterface;

    /**
     * @return ResponseInterface A `204` with no body: nothing changed (#193).
     */
    public function unchanged(): ResponseInterface;

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
     * @param int    $status   The status.
     * @param string $document An `XmlDocument`, serialized (#233). Only `XmlResponse` calls this: the response
     *                         shapes are sealed in `AIC\Controller\Domain`, and no module is handed a writer.
     *
     * @return ResponseInterface The response, `application/xml; charset=utf-8`.
     */
    public function xml(int $status, string $document): ResponseInterface;

    /**
     * @param string                 $path          The file to send.
     * @param string                 $mediaType     Its `Content-Type`.
     * @param string                 $downloadName  The name the browser saves it as.
     * @param ServerRequestInterface $serverRequest The request: its `Range` and conditions (#196).
     *
     * @return ResponseInterface The response: 200, 206 for a range, 416 for one the file cannot satisfy, or
     *                           304 or 412 for a condition.
     */
    public function download(
        string $path,
        string $mediaType,
        string $downloadName,
        ServerRequestInterface $serverRequest,
    ): ResponseInterface;

    /**
     * @param EventSource $eventSource Gives the events.
     *
     * @return ResponseInterface The `text/event-stream` response, its body pulling the events as it is read.
     */
    public function events(EventSource $eventSource): ResponseInterface;
}
