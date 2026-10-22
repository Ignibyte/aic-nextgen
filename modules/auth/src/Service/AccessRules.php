<?php

declare(strict_types=1);

namespace App\Auth\Service;

use AIC\Auth\Domain\RouteAuthConfig;

/**
 * The sign-in rules (#93), a factory the manifest lists: every module's
 * protected path prefixes, and the sign-in page an anonymous visitor to one is
 * sent to.
 */
final readonly class AccessRules
{
    /**
     * Where an anonymous visitor to a protected path signs in.
     */
    private const string LOGIN_PATH = '/login';

    /**
     * @param list<string> $protectedPrefixes Every module's protected path prefixes.
     */
    public function __construct(
        private array $protectedPrefixes,
    ) {
    }

    /**
     * @return RouteAuthConfig The paths the sign-in middleware guards, and where it sends a visitor.
     */
    public function routeAuthConfig(): RouteAuthConfig
    {
        return new RouteAuthConfig($this->protectedPrefixes, self::LOGIN_PATH);
    }
}
