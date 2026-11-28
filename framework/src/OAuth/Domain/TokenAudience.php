<?php

declare(strict_types=1);

namespace AIC\OAuth\Domain;

use function rtrim;
use function str_contains;
use function str_starts_with;
use function strlen;
use function substr;

/**
 * A token's audience (RFC 8707, #352): the `resource` a client asked for,
 * kept as the path of this site it names (`/mcp` for
 * `https://example.test/mcp`, `/` for the site itself). A token with no
 * resource is bound to nothing and holds on every path, as before #352; a
 * bound one holds on its path and the paths below it, on a segment boundary.
 */
final readonly class TokenAudience
{
    /**
     * @param string      $resource    The `resource` parameter, an absolute URL; empty for none.
     * @param OAuthServer $oauthServer The server, whose issuer the resource must be under.
     *
     * @return string|null The path it names (empty for no resource), or null when it is another site's, or carries a
     *                     query or a fragment.
     */
    public function path(string $resource, OAuthServer $oauthServer): ?string
    {
        if ($resource === '') {
            return '';
        }

        $issuer = rtrim($oauthServer->issuer, '/');

        if (
            !str_starts_with($resource . '/', $issuer . '/')
            || str_contains($resource, '?')
            || str_contains($resource, '#')
        ) {
            return null;
        }

        $path = substr($resource, strlen($issuer));

        return $path === ''
            ? '/'
            : $path;
    }

    /**
     * @param string $bound The path a token is bound to; empty for none.
     * @param string $path  A request's path.
     *
     * @return bool Whether the token holds there.
     */
    public function covers(string $bound, string $path): bool
    {
        if ($bound === '' || $bound === '/' || $path === $bound) {
            return true;
        }

        return str_starts_with($path, rtrim($bound, '/') . '/');
    }
}
