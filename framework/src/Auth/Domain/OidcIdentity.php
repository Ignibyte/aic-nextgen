<?php

declare(strict_types=1);

namespace AIC\Auth\Domain;

/**
 * Who a provider signed in (#313): the provider's name, the subject its id
 * token names (stable for that person at that issuer) and, when the token
 * carries one, an email.
 */
final readonly class OidcIdentity
{
    /**
     * @param string $provider The provider's name in `config/app.php`.
     * @param string $subject  The `sub` claim.
     * @param string $email    The `email` claim; empty when absent.
     */
    public function __construct(
        public string $provider,
        public string $subject,
        public string $email,
    ) {
    }
}
