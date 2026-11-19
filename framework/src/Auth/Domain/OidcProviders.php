<?php

declare(strict_types=1);

namespace AIC\Auth\Domain;

use AIC\Auth\Contracts\SignInRefusedException;

/**
 * The OpenID Connect providers the app names in `config/app.php` (#313), by
 * name; none by default.
 */
final readonly class OidcProviders
{
    /**
     * @param array<string, OidcProvider> $providers Each provider, by the name its sign-in uses.
     */
    public function __construct(
        private array $providers = [],
    ) {
    }

    /**
     * @param string $name A provider's name.
     *
     * @throws SignInRefusedException When the app names no such provider.
     *
     * @return OidcProvider The provider.
     */
    public function provider(string $name): OidcProvider
    {
        return $this->providers[$name] ?? throw new SignInRefusedException('No sign-in provider is named ' . $name . '.');
    }
}
