<?php

declare(strict_types=1);

namespace AIC\Auth\Domain;

use InvalidArgumentException;
use Psr\Http\Message\UriInterface;
use SensitiveParameter;

use function in_array;
use function parse_url;
use function str_starts_with;

use const PHP_URL_HOST;
use const PHP_URL_SCHEME;

/**
 * An OpenID Connect provider the app signs people in through (#313), as
 * `config/app.php` names it: its issuer, two endpoints and its key set
 * (https, or http on a loopback host for a local stub), the app's client id
 * and secret there (an empty secret is a public client, which PKCE still
 * protects), the callback (a path on the request's origin, or an absolute
 * URL), the scopes it asks for and the algorithms its id tokens are signed
 * with, `RS256` or `ES256` (#343).
 */
final readonly class OidcProvider
{
    /**
     * The hosts plain http is allowed on.
     */
    private const array LOOPBACK = ['127.0.0.1', 'localhost', '[::1]'];

    /**
     * The signature algorithms an id token may be checked by (#343).
     */
    private const array ALGORITHMS = ['RS256', 'ES256'];

    /**
     * @param string       $issuer                The issuer the id token must name (`iss`).
     * @param string       $authorizationEndpoint Where the browser is sent to sign in.
     * @param string       $tokenEndpoint         Where the code is exchanged.
     * @param string       $jwksUri               Where its signing keys are published, a JSON Web Key Set (#343).
     * @param string       $clientId              The app's client id at the provider.
     * @param string       $clientSecret          Its secret, from the environment; empty for a public client.
     * @param string       $callback              The redirect URI: a path such as `/sign-in/oidc/callback`, or an absolute URL.
     * @param string       $scopes                The scopes asked for, space-separated; `openid` is always one.
     * @param list<string> $algorithms            The algorithms its id tokens may be signed with: `RS256`, `ES256` or both.
     *
     * @throws InvalidArgumentException When a URL is not https (or http on a loopback host), the client or callback is
     *                                  empty, or an algorithm is none of RS256 and ES256.
     */
    public function __construct(
        public string $issuer,
        public string $authorizationEndpoint,
        public string $tokenEndpoint,
        public string $jwksUri,
        public string $clientId,
        #[SensitiveParameter]
        public string $clientSecret,
        public string $callback,
        public string $scopes = 'openid email profile',
        public array $algorithms = ['RS256'],
    ) {
        foreach ([$issuer, $authorizationEndpoint, $tokenEndpoint, $jwksUri] as $url) {
            self::secure($url);
        }

        self::known($algorithms);

        if ($clientId === '' || $callback === '' || !str_starts_with($scopes . ' ', 'openid ')) {
            throw new InvalidArgumentException('An OIDC provider needs a client id, a callback and scopes that start with openid.');
        }
    }

    /**
     * @param UriInterface $uri The request's URI, whose origin a path callback joins.
     *
     * @return string The redirect URI the provider sends the browser back to.
     */
    public function redirectUri(UriInterface $uri): string
    {
        if (!str_starts_with($this->callback, '/')) {
            return $this->callback;
        }

        return $uri->getScheme() . '://' . $uri->getAuthority() . $this->callback;
    }

    /**
     * @param list<string> $algorithms The algorithms named.
     *
     * @throws InvalidArgumentException When there are none, or one is neither RS256 nor ES256.
     */
    private static function known(array $algorithms): void
    {
        if ($algorithms === []) {
            throw new InvalidArgumentException('An OIDC provider signs its id tokens with RS256, ES256 or both.');
        }

        foreach ($algorithms as $algorithm) {
            if (!in_array($algorithm, self::ALGORITHMS, true)) {
                throw new InvalidArgumentException('An OIDC provider signs with RS256 or ES256, not ' . $algorithm . '.');
            }
        }
    }

    /**
     * @param string $url An endpoint or the issuer.
     *
     * @throws InvalidArgumentException When it is neither https nor http on a loopback host.
     */
    private static function secure(string $url): void
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);
        $host = parse_url($url, PHP_URL_HOST);

        if ($scheme === 'https' || ($scheme === 'http' && in_array($host, self::LOOPBACK, true))) {
            return;
        }

        throw new InvalidArgumentException('An OIDC provider URL must be https (http only on a loopback host): ' . $url . '.');
    }
}
