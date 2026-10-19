<?php

declare(strict_types=1);

namespace AIC\Http\Application;

use InvalidArgumentException;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use RuntimeException;

/**
 * Makes a response with a content type and a body, and sets headers on one.
 *
 * PSR-7's message withers declare `MessageInterface`; a response's copy is still
 * a response, and this says so once, for elephc, which types a result by its
 * declared return.
 */
final readonly class ResponseBuilder
{
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
        $response = $this->withHeader($this->responseFactory->createResponse($status), 'Content-Type', $contentType);

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
        $response = $this->withHeader($this->responseFactory->createResponse($status), 'Content-Type', $contentType);

        return self::response($response->withBody($this->streamFactory->createStreamFromFile($path, 'rb')));
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
