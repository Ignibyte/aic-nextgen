<?php

declare(strict_types=1);

namespace AIC\OAuth\Domain;

use function implode;

/**
 * An authorization request whose client and redirect URI passed their checks
 * (#331): the client, the registered redirect URI it returns to, the scopes
 * asked for, the client's state and its PKCE S256 challenge. When a later
 * check failed, `refusal` holds the error the client is sent back with;
 * otherwise it is empty and the consent page shows the request and sends it
 * back with the person's decision.
 */
final readonly class AuthorizationRequest
{
    /**
     * @param string       $clientId    The client.
     * @param string       $redirectUri Where the answer goes, one the client registered.
     * @param list<string> $scopes      The scopes asked for, each one the client may hold.
     * @param string       $state       The client's state, returned as given; may be empty.
     * @param string       $challenge   The PKCE S256 code challenge.
     * @param string       $resource    The resource asked for (RFC 8707); empty for none.
     * @param string       $refusal     The error to send the client back with; empty when the request is sound.
     */
    public function __construct(
        public string $clientId,
        public string $redirectUri,
        public array $scopes,
        public string $state,
        public string $challenge,
        public string $resource = '',
        public string $refusal = '',
    ) {
    }

    /**
     * @return array<string, string> The fields the consent form sends back, as the request named them.
     */
    public function fields(): array
    {
        return [
            'client_id' => $this->clientId,
            'code_challenge' => $this->challenge,
            'code_challenge_method' => 'S256',
            'redirect_uri' => $this->redirectUri,
            'resource' => $this->resource,
            'response_type' => 'code',
            'scope' => implode(' ', $this->scopes),
            'state' => $this->state,
        ];
    }
}
