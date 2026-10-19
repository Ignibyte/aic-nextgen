<?php

declare(strict_types=1);

namespace AIC\Controller\Application;

use AIC\Controller\Contracts\ResponseWriter;
use AIC\Http\Application\ResponseBuilder;
use AIC\Routing\Domain\RouteNotFoundException;
use JsonException;
use JsonSerializable;
use Override;
use Psr\Http\Message\ResponseInterface;

use function is_file;
use function json_encode;
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
     *
     * @throws RouteNotFoundException When the file is not there; answered with 404.
     *
     * @return ResponseInterface The file, as an attachment.
     */
    #[Override]
    public function download(string $path, string $mediaType, string $downloadName): ResponseInterface
    {
        if (!is_file($path)) {
            throw new RouteNotFoundException($path);
        }

        return $this->responseBuilder->withHeader(
            $this->responseBuilder->createFromFile(200, $mediaType, $path),
            'Content-Disposition',
            'attachment; filename="' . str_replace('\\', '', str_replace('"', '', $downloadName)) . '"',
        );
    }
}
