<?php

declare(strict_types=1);

namespace AIC\Auth\Domain;

use InvalidArgumentException;

use function array_any;
use function str_starts_with;

/**
 * Which paths need a signed-in user, and where the login page is (#9). A
 * prefix protects itself and the paths below it.
 */
final readonly class RouteAuthConfig
{
    /**
     * @param list<string> $protectedPrefixes Paths such as `/account`.
     * @param string       $loginPath         The login page, a path on this site.
     *
     * @throws InvalidArgumentException When the login path is not a path on this site.
     */
    public function __construct(
        private array $protectedPrefixes,
        public string $loginPath,
    ) {
        if (!str_starts_with($loginPath, '/') || str_starts_with($loginPath, '//')) {
            throw new InvalidArgumentException('The login path must be a path on this site; got "' . $loginPath . '".');
        }
    }

    /**
     * @param string $path A request's path.
     *
     * @return bool Whether it needs a signed-in user.
     */
    public function protects(string $path): bool
    {
        return array_any(
            $this->protectedPrefixes,
            static fn (string $prefix): bool => $path === $prefix || str_starts_with($path, $prefix . '/'),
        );
    }
}
