<?php

declare(strict_types=1);

namespace AIC\OAuth\Domain;

use function array_all;
use function in_array;

/**
 * A client the operator registered (#331): its id, whether it holds a secret
 * (a confidential client) or not (a public one, which may only use the
 * authorization code grant with PKCE), the scopes it may be granted and the
 * redirect URIs an authorization may send the browser back to.
 */
final readonly class OAuthClient
{
    /**
     * @param string       $id           Its client id.
     * @param bool         $confidential Whether it authenticates with a secret.
     * @param list<string> $scopes       The scopes it may hold.
     * @param list<string> $redirectUris Where an authorization may return to, compared exactly.
     * @param string       $createdAt    When it was added.
     */
    public function __construct(
        public string $id,
        public bool $confidential,
        public array $scopes,
        public array $redirectUris,
        public string $createdAt,
    ) {
    }

    /**
     * @param string $redirectUri A redirect URI a request names.
     *
     * @return bool Whether it is one the client registered.
     */
    public function returnsTo(string $redirectUri): bool
    {
        return in_array($redirectUri, $this->redirectUris, true);
    }

    /**
     * @param list<string> $scopes Scopes asked for.
     *
     * @return bool Whether the client may hold every one.
     */
    public function holds(array $scopes): bool
    {
        $own = $this->scopes;

        return array_all($scopes, static fn (string $scope): bool => in_array($scope, $own, true));
    }
}
