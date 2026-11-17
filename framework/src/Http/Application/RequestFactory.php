<?php

declare(strict_types=1);

namespace AIC\Http\Application;

use AIC\Http\Domain\Request;
use InvalidArgumentException;
use Override;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Message\UriInterface;

/**
 * The PSR-17 factory of the requests an app sends (#305), each with an empty
 * body until the caller gives it one.
 */
final readonly class RequestFactory implements RequestFactoryInterface
{
    /**
     * @param UriFactoryInterface    $uriFactory    Parses a URI given as text.
     * @param StreamFactoryInterface $streamFactory Makes the empty body.
     */
    public function __construct(
        private UriFactoryInterface $uriFactory = new UriFactory(),
        private StreamFactoryInterface $streamFactory = new StreamFactory(),
    ) {
    }

    /**
     * @param string              $method The request method.
     * @param UriInterface|string $uri    The URI, parsed when it is text.
     *
     * @throws InvalidArgumentException When the method or the URI is invalid.
     *
     * @return RequestInterface The request, with an empty body.
     */
    #[Override]
    public function createRequest(string $method, $uri): RequestInterface
    {
        $parsed = $uri instanceof UriInterface
            ? $uri
            : $this->uriFactory->createUri($uri);

        return new Request($method, $parsed, $this->streamFactory->createStream());
    }
}
