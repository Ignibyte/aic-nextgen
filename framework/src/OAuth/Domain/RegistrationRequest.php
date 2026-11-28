<?php

declare(strict_types=1);

namespace AIC\OAuth\Domain;

/**
 * A client's checked registration request (RFC 7591 section 2, #352), as
 * `RegistrationReader` reads it: where an authorization may return to, how
 * the client authenticates at the token endpoint, the grants it uses and the
 * scopes it may hold.
 */
final readonly class RegistrationRequest
{
    /**
     * @param list<string> $redirectUris Where an authorization may return to.
     * @param string       $authMethod   How it authenticates at the token endpoint.
     * @param list<string> $grantTypes   The grants it uses.
     * @param list<string> $scopes       The scopes it may hold.
     */
    public function __construct(
        public array $redirectUris,
        public string $authMethod,
        public array $grantTypes,
        public array $scopes,
    ) {
    }

    /**
     * @return bool Whether the client authenticates with a secret.
     */
    public function confidential(): bool
    {
        return $this->authMethod !== 'none';
    }
}
