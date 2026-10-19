<?php

declare(strict_types=1);

namespace AIC\Http\Application;

use AIC\Http\Domain\ServerRequest;
use InvalidArgumentException;
use Override;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Message\UriInterface;

use function array_filter;
use function is_scalar;
use function is_string;

use const ARRAY_FILTER_USE_KEY;

/**
 * The PSR-17 server request factory: a request with no headers, an empty body
 * and the scalar server params it is given.
 */
final readonly class ServerRequestFactory implements ServerRequestFactoryInterface
{
    /**
     * @param UriFactoryInterface    $uriFactory    Parses a string URI.
     * @param StreamFactoryInterface $streamFactory Makes the empty body.
     */
    public function __construct(
        private UriFactoryInterface $uriFactory,
        private StreamFactoryInterface $streamFactory,
    ) {
    }

    /**
     * @param string                  $method       The request method.
     * @param mixed                   $uri          A URI or a string; either is parsed from its string form.
     * @param array<array-key, mixed> $serverParams The server params; entries that are not scalars keyed by name are left out.
     *
     * @throws InvalidArgumentException When the URI is neither, or the method is invalid.
     *
     * @return ServerRequestInterface The request.
     */
    #[Override]
    public function createServerRequest(string $method, mixed $uri, array $serverParams = []): ServerRequestInterface
    {
        if (!$uri instanceof UriInterface && !is_string($uri)) {
            throw new InvalidArgumentException('A URI must be a string or a UriInterface.');
        }

        return new ServerRequest(
            method: $method,
            uri: $this->uriFactory->createUri((string) $uri),
            stream: $this->streamFactory->createStream(),
            serverParams: array_filter(array_filter($serverParams, is_scalar(...)), is_string(...), ARRAY_FILTER_USE_KEY),
        );
    }
}
