<?php

declare(strict_types=1);

namespace AIC\Http\Application;

use AIC\Http\Domain\Uri;
use InvalidArgumentException;
use Override;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Message\UriInterface;

use function is_array;
use function parse_url;

/**
 * The PSR-17 URI factory.
 */
final readonly class UriFactory implements UriFactoryInterface
{
    /**
     * @param string $uri The URI to parse.
     *
     * @throws InvalidArgumentException When the URI cannot be parsed.
     *
     * @return UriInterface The parsed URI.
     */
    #[Override]
    public function createUri(string $uri = ''): UriInterface
    {
        $parts = parse_url($uri);

        if (!is_array($parts)) {
            throw new InvalidArgumentException('Invalid URI: ' . $uri);
        }

        $user = $parts['user'] ?? '';
        $password = $parts['pass'] ?? '';

        return new Uri(
            scheme: $parts['scheme'] ?? '',
            userInfo: $user === '' || $password === '' ? $user : $user . ':' . $password,
            host: $parts['host'] ?? '',
            port: $parts['port'] ?? null,
            path: $parts['path'] ?? '',
            query: $parts['query'] ?? '',
            fragment: $parts['fragment'] ?? '',
        );
    }
}
