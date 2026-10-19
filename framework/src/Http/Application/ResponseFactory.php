<?php

declare(strict_types=1);

namespace AIC\Http\Application;

use AIC\Http\Domain\Response;
use InvalidArgumentException;
use Override;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * The PSR-17 response factory: a response with no headers and an empty body.
 */
final readonly class ResponseFactory implements ResponseFactoryInterface
{
    /**
     * @param StreamFactoryInterface $streamFactory Makes each response's empty body.
     */
    public function __construct(
        private StreamFactoryInterface $streamFactory,
    ) {
    }

    /**
     * @param int    $code         The status, 100 to 599.
     * @param string $reasonPhrase The reason phrase; empty takes the registered one.
     *
     * @throws InvalidArgumentException When the status is out of range.
     *
     * @return ResponseInterface The response.
     */
    #[Override]
    public function createResponse(int $code = 200, string $reasonPhrase = ''): ResponseInterface
    {
        return new Response($code, $this->streamFactory->createStream(), reasonPhrase: $reasonPhrase);
    }
}
