<?php

declare(strict_types=1);

namespace AIC\Http\Domain;

use InvalidArgumentException;
use Override;
use Psr\Http\Message\UriInterface;

use function is_int;
use function preg_match;

/**
 * The PSR-7 request methods: request target, method and URI.
 *
 * A trait so ServerRequest keeps its own methods within the class limit while
 * the request half of PSR-7 lives beside it; the using class extends Message.
 */
trait RequestBehavior
{
    /**
     * A request method: an RFC 9110 token.
     */
    private const string METHOD_PATTERN = '/^[!#$%&\'*+\\-.^_`|~0-9A-Za-z]+$/';

    /**
     * The request method, as sent.
     */
    protected string $method;

    /**
     * An explicit request target, or null to derive it from the URI.
     */
    protected ?string $requestTarget = null;

    /**
     * The request URI.
     */
    protected UriInterface $uri;

    /**
     * @return string The request target: the explicit one, or the URI's path and query.
     */
    #[Override]
    public function getRequestTarget(): string
    {
        if ($this->requestTarget !== null) {
            return $this->requestTarget;
        }

        $target = $this->uri->getPath() === ''
            ? '/'
            : $this->uri->getPath();

        return $this->uri->getQuery() === ''
            ? $target
            : $target . '?' . $this->uri->getQuery();
    }

    /**
     * @param string $requestTarget The request target for the copy.
     *
     * @return static A copy with that request target.
     */
    #[Override]
    public function withRequestTarget(string $requestTarget): static
    {
        $new = clone $this;
        $new->requestTarget = $requestTarget;

        return $new;
    }

    /**
     * @return string The request method.
     */
    #[Override]
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * @param string $method The request method for the copy.
     *
     * @throws InvalidArgumentException When the method is not a token.
     *
     * @return static A copy with that method.
     */
    #[Override]
    public function withMethod(string $method): static
    {
        self::assertMethod($method);
        $new = clone $this;
        $new->method = $method;

        return $new;
    }

    /**
     * @return UriInterface The request URI.
     */
    #[Override]
    public function getUri(): UriInterface
    {
        return $this->uri;
    }

    /**
     * @param UriInterface $uri          The URI for the copy.
     * @param bool         $preserveHost Keep an existing Host header instead of the URI's host.
     *
     * @throws InvalidArgumentException When a constructed value or argument is invalid.
     *
     * @return static A copy with that URI.
     */
    #[Override]
    public function withUri(UriInterface $uri, bool $preserveHost = false): static
    {
        $new = clone $this;
        $new->uri = $uri;

        if (!$preserveHost || !$this->hasHeader('Host')) {
            $new->hostFromUri($uri);
        }

        return $new;
    }

    /**
     * Set the Host header from the URI unless the request already has one.
     *
     * @param UriInterface $uri The URI to take the host from.
     *
     * @throws InvalidArgumentException When a constructed value or argument is invalid.
     */
    private function initialHostFromUri(UriInterface $uri): void
    {
        if ($this->hasHeader('Host')) {
            return;
        }

        $this->hostFromUri($uri);
    }

    /**
     * Set the Host header from the URI's host and port, first among the headers;
     * nothing happens when the URI has no host.
     *
     * @param UriInterface $uri The URI to take the host from.
     *
     * @throws InvalidArgumentException When a constructed value or argument is invalid.
     */
    private function hostFromUri(UriInterface $uri): void
    {
        $host = $uri->getHost();

        if ($host === '') {
            return;
        }

        $port = $uri->getPort();
        $this->prependHeader('Host', is_int($port) ? $host . ':' . $port : $host);
    }

    /**
     * @param string $method The request method to check.
     *
     * @throws InvalidArgumentException When the method is not a token.
     */
    private static function assertMethod(string $method): void
    {
        if (preg_match(self::METHOD_PATTERN, $method) !== 1) {
            throw new InvalidArgumentException('Invalid HTTP method: ' . $method);
        }
    }
}
