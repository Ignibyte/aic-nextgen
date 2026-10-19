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
 * seal is in the types. elephc does not narrow an object-typed value with
 * `instanceof`, which is the other reason for the design (plan.md).
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
     *
     * @return ResponseInterface The response.
     */
    public function download(string $path, string $mediaType, string $downloadName): ResponseInterface;
}
