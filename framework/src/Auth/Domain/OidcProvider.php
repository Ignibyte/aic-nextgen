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
 * `config/app.php` names it: its issuer and two endpoints (https, or http on
 * a loopback host for a local stub), the app's client id and secret there
 * (an empty secret is a public client, which PKCE still protects), the
 * callback (a path on the request's origin, or an absolute URL) and the
 * scopes it asks for.
 */
final readonly class OidcProvider
{
    /**
     * The hosts plain http is allowed on.
     */
    private const array LOOPBACK = ['127.0.0.1', 'localhost', '[::1]'];

    /**
     * @param string $issuer                The issuer the id token must name (`iss`).
     * @param string $authorizationEndpoint Where the browser is sent to sign in.
     * @param string $tokenEndpoint         Where the code is exchanged.
     * @param string $clientId              The app's client id at the provider.
     * @param string $clientSecret          Its secret, from the environment; empty for a public client.
     * @param string $callback              The redirect URI: a path such as `/sign-in/oidc/callback`, or an absolute URL.
     * @param string $scopes                The scopes asked for, space-separated; `openid` is always one.
     *
     * @throws InvalidArgumentException When a URL is not https (or http on a loopback host), or the client or callback is empty.
     */
    public function __construct(
        public string $issuer,
        public string $authorizationEndpoint,
        public string $tokenEndpoint,
        public string $clientId,
        #[SensitiveParameter]
        public string $clientSecret,
        public string $callback,
        public string $scopes = 'openid email profile',
    ) {
        foreach ([$issuer, $authorizationEndpoint, $tokenEndpoint] as $url) {
            self::secure($url);
        }

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
