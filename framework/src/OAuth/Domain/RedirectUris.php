<?php

declare(strict_types=1);

namespace AIC\OAuth\Domain;

use function in_array;
use function parse_url;
use function str_contains;

use const PHP_URL_HOST;
use const PHP_URL_SCHEME;

/**
 * The redirect URIs a client may register (#331, shared by `client:add`
 * and the registration endpoint since #352): https, or http on a loopback
 * host (RFC 8252's native apps), with a host and no fragment or space. An
 * authorization compares a request's redirect URI with these exactly.
 */
final readonly class RedirectUris
{
    /**
     * The hosts plain http is allowed on.
     */
    private const array LOOPBACK = ['127.0.0.1', 'localhost', '[::1]'];

    /**
     * @param string $uri A redirect URI.
     *
     * @return bool Whether it is https, or http on a loopback host, with a host and no fragment.
     */
    public function accepts(string $uri): bool
    {
        $scheme = parse_url($uri, PHP_URL_SCHEME);
        $host = parse_url($uri, PHP_URL_HOST);

        return !str_contains($uri, '#')
            && !str_contains($uri, ' ')
            && ($scheme === 'https' || ($scheme === 'http' && in_array($host, self::LOOPBACK, true)))
            && $host !== null
            && $host !== '';
    }
}
