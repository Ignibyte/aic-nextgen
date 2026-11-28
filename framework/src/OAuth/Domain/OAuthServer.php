<?php

declare(strict_types=1);

namespace AIC\OAuth\Domain;

use InvalidArgumentException;

use function in_array;
use function min;
use function parse_url;
use function rtrim;
use function str_starts_with;

use const PHP_URL_HOST;
use const PHP_URL_PATH;
use const PHP_URL_SCHEME;

/**
 * The app as an OAuth 2 authorization server (#331), as `config/app.php`
 * sets it: its issuer (the site's own https origin, or http on a loopback
 * host), where a module answers the authorization endpoint, how long an
 * access token, an authorization code and a refresh token live (#352), and
 * who may register a client and with which scopes. With no issuer it is
 * off: the endpoints and the metadata answer nothing and a refusal carries
 * no `resource_metadata`.
 */
final readonly class OAuthServer
{
    /**
     * The token endpoint's path.
     */
    public const string TOKEN_PATH = '/oauth/token';

    /**
     * The revocation endpoint's path (RFC 7009).
     */
    public const string REVOKE_PATH = '/oauth/revoke';

    /**
     * The introspection endpoint's path (RFC 7662).
     */
    public const string INTROSPECT_PATH = '/oauth/introspect';

    /**
     * The registration endpoint's path (RFC 7591).
     */
    public const string REGISTER_PATH = '/oauth/register';

    /**
     * The authorization server's metadata (RFC 8414).
     */
    public const string SERVER_METADATA_PATH = '/.well-known/oauth-authorization-server';

    /**
     * The protected resource's metadata (RFC 9728).
     */
    public const string RESOURCE_METADATA_PATH = '/.well-known/oauth-protected-resource';

    /**
     * The hosts plain http is allowed on.
     */
    private const array LOOPBACK = ['127.0.0.1', 'localhost', '[::1]'];

    /**
     * @param string             $issuer             The issuer, the site's origin (`https://example.test`); empty to turn
     *                                               it off.
     * @param string             $authorizationPath  The path a module routes the authorization endpoint to.
     * @param int                $tokenSeconds       How long an access token lives.
     * @param int                $codeSeconds        How long an authorization code lives.
     * @param int                $refreshSeconds     How long a refresh token lives, renewed by each use.
     * @param ClientRegistration $registration       Who may register a client at the registration endpoint.
     * @param list<string>       $registrationScopes The most a registered client may hold; none when registration is off.
     *
     * @throws InvalidArgumentException When the issuer is not https (or http on a loopback host) with no path, the
     *                                  path does not start with `/`, a lifetime is below one second, or registration
     *                                  is on with no scopes to give.
     */
    public function __construct(
        public string $issuer = '',
        public string $authorizationPath = '/oauth/authorize',
        public int $tokenSeconds = 3_600,
        public int $codeSeconds = 60,
        public int $refreshSeconds = 2_592_000,
        public ClientRegistration $registration = ClientRegistration::Off,
        public array $registrationScopes = [],
    ) {
        if ($issuer !== '') {
            self::origin($issuer);
        }

        if (!str_starts_with($authorizationPath, '/') || min($tokenSeconds, $codeSeconds, $refreshSeconds) < 1) {
            throw new InvalidArgumentException(
                'OAuthServer: the authorization path must start with / and each lifetime be at least a second.',
            );
        }

        if (($registration === ClientRegistration::Off) !== ($registrationScopes === [])) {
            throw new InvalidArgumentException(
                'OAuthServer: registration needs the scopes a registered client may hold, and only registration does.',
            );
        }
    }

    /**
     * @return bool Whether a client may register itself.
     */
    public function registers(): bool
    {
        return $this->enabled() && $this->registration !== ClientRegistration::Off;
    }

    /**
     * @return bool Whether the app is an authorization server.
     */
    public function enabled(): bool
    {
        return $this->issuer !== '';
    }

    /**
     * @param string $path One of the server's paths.
     *
     * @return string Its absolute URL.
     */
    public function url(string $path): string
    {
        return rtrim($this->issuer, '/') . $path;
    }

    /**
     * @return string The `resource_metadata` a refusal names; empty when the server is off.
     */
    public function resourceMetadata(): string
    {
        return $this->enabled()
            ? $this->url(self::RESOURCE_METADATA_PATH)
            : '';
    }

    /**
     * @param string $issuer The issuer.
     *
     * @throws InvalidArgumentException When it is neither https nor http on a loopback host, or has a path.
     */
    private static function origin(string $issuer): void
    {
        $scheme = parse_url($issuer, PHP_URL_SCHEME);
        $loopback = $scheme === 'http' && in_array(parse_url($issuer, PHP_URL_HOST), self::LOOPBACK, true);

        if (in_array(parse_url($issuer, PHP_URL_PATH), [null, '/'], true) && ($scheme === 'https' || $loopback)) {
            return;
        }

        throw new InvalidArgumentException(
            'OAuthServer: the issuer must be an https origin (http only on a loopback host): ' . $issuer . '.',
        );
    }
}
