<?php

declare(strict_types=1);

namespace AIC\Http\Domain;

use InvalidArgumentException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

/**
 * The PSR-7 request an app sends (#305): a method, a URI and a body, with the
 * `Host` header taken from the URI. The server side's request is
 * `ServerRequest`, which adds the SAPI's input.
 */
final class Request extends Message implements RequestInterface
{
    use RequestBehavior;

    /**
     * @param string          $method          The request method.
     * @param UriInterface    $uri             The request URI.
     * @param StreamInterface $stream          The request body.
     * @param string          $protocolVersion The HTTP version.
     *
     * @throws InvalidArgumentException When the method is invalid.
     */
    public function __construct(
        string $method,
        UriInterface $uri,
        StreamInterface $stream,
        string $protocolVersion = '1.1',
    ) {
        self::assertMethod($method);

        parent::__construct($protocolVersion, $stream);

        $this->method = $method;
        $this->uri = $uri;
        $this->initialHostFromUri($uri);
    }
}
