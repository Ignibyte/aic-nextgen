<?php

declare(strict_types=1);

namespace AIC\Http\Application;

use InvalidArgumentException;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

use function str_starts_with;

/**
 * Makes a response with a content type and a body, and sets headers on one.
 *
 * PSR-7's message withers declare `MessageInterface`; a response's copy is still
 * a response, and this says so once, for elephc, which types a result by its
 * declared return. Every HTML response it makes carries a strict
 * Content-Security-Policy (#173): scripts, styles, fonts and connections from
 * the site itself only, so no inline script or style runs. Tessera's csp build
 * and the HTML lint (#170) keep pages within it.
 */
final readonly class ResponseBuilder
{
    /**
     * The policy on every HTML response.
     */
    public const string POLICY = "default-src 'self'; img-src 'self' data:; object-src 'none'; base-uri 'self';"
        . " form-action 'self'; frame-ancestors 'none'";

    /**
     * @param ResponseFactoryInterface $responseFactory Makes the response.
     * @param StreamFactoryInterface   $streamFactory   Makes its body.
     */
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
        private StreamFactoryInterface $streamFactory,
    ) {
    }

    /**
     * @param int    $status      The status, 100 to 599.
     * @param string $contentType The `Content-Type`, such as `text/html; charset=utf-8`.
     * @param string $body        The body.
     *
     * @throws InvalidArgumentException When the status is out of range.
     *
     * @return ResponseInterface The response.
     */
    public function create(int $status, string $contentType, string $body): ResponseInterface
    {
        $response = $this->head($status, $contentType);

        return self::response($response->withBody($this->streamFactory->createStream($body)));
    }

    /**
     * @param int    $status      The status, 100 to 599.
     * @param string $contentType The `Content-Type`.
     * @param string $path        The file whose bytes are the body.
     *
     * @throws InvalidArgumentException When the status is out of range.
     * @throws RuntimeException         When the file cannot be opened.
     *
     * @return ResponseInterface The response.
     */
    public function createFromFile(int $status, string $contentType, string $path): ResponseInterface
    {
        $response = $this->head($status, $contentType);

        return self::response($response->withBody($this->streamFactory->createStreamFromFile($path, 'rb')));
    }

    /**
     * @param int             $status      The status, 100 to 599.
     * @param string          $contentType The `Content-Type`.
     * @param StreamInterface $stream      The body stream, read as the response is sent.
     *
     * @throws InvalidArgumentException When the status is out of range.
     *
     * @return ResponseInterface The response.
     */
    public function createFromStream(int $status, string $contentType, StreamInterface $stream): ResponseInterface
    {
        $response = $this->head($status, $contentType);

        return self::response($response->withBody($stream));
    }

    /**
     * @param ResponseInterface $response A response.
     * @param string            $name     The header name.
     * @param string            $value    Its value.
     *
     * @throws InvalidArgumentException When the name or the value is invalid.
     *
     * @return ResponseInterface A copy with the header set.
     */
    public function withHeader(ResponseInterface $response, string $name, string $value): ResponseInterface
    {
        return self::response($response->withHeader($name, $value));
    }

    /**
     * @param ResponseInterface $response A response.
     * @param string            $name     A header name, such as `Set-Cookie`, that may carry several values.
     * @param string            $value    One more value.
     *
     * @throws InvalidArgumentException When the name or value is not valid.
     *
     * @return ResponseInterface The response with the value added to the header's.
     */
    public function withAddedHeader(ResponseInterface $response, string $name, string $value): ResponseInterface
    {
        return self::response($response->withAddedHeader($name, $value));
    }

    /**
     * @param int    $status      The status.
     * @param string $contentType The response media type.
     *
     * @throws InvalidArgumentException When a response field is invalid.
     *
     * @return ResponseInterface Conservative defaults for every application and boundary response.
     */
    private function head(int $status, string $contentType): ResponseInterface
    {
        $response = $this->withHeader($this->responseFactory->createResponse($status), 'Content-Type', $contentType);
        $response = $this->withHeader($response, 'Cache-Control', 'private, no-store');

        return str_starts_with($contentType, 'text/html')
            ? $this->withHeader($response, 'Content-Security-Policy', self::POLICY)
            : $response;
    }

    /**
     * @param MessageInterface $message A response's copy.
     *
     * @throws InvalidArgumentException When it is not a response.
     *
     * @return ResponseInterface The copy.
     */
    private static function response(MessageInterface $message): ResponseInterface
    {
        if (!$message instanceof ResponseInterface) {
            throw new InvalidArgumentException('A response wither must return a response.');
        }

        return $message;
    }
}
