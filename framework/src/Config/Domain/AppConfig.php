<?php

declare(strict_types=1);

namespace AIC\Config\Domain;

use AIC\Auth\Domain\OidcProviders;
use AIC\Broadcast\Domain\BroadcastConfig;
use AIC\Http\Domain\Webhooks;
use AIC\HttpClient\Domain\Upstreams;
use AIC\OAuth\Domain\OAuthServer;
use AIC\Tessera\Domain\TesseraRelease;
use InvalidArgumentException;

use function trim;

/**
 * The application's own settings, as `config/app.php` returns them.
 *
 * Config in AIC is typed: each concern has a readonly object whose properties
 * are the settings, and its config file constructs it. A setting that does not
 * exist cannot be read, and a value of the wrong type cannot be stored.
 */
final readonly class AppConfig
{
    /**
     * The request body cap when the app names none: 8 MiB, elephc-web's default.
     */
    public const int MAX_BODY_BYTES = 8_388_608;

    /**
     * @param string          $name         The application's name, as pages show it.
     * @param TesseraRelease  $tessera      The Tessera release the pages load (#70).
     * @param string|null     $theme        The folder under `themes/` the build reads the components, layouts
     *                                      and assets from (#153); null for an app with no HTML.
     * @param int             $maxBodyBytes The largest request body the app takes (#196); a larger one answers
     *                                      413 before the session. The default is the compiled server's own.
     * @param Upstreams       $upstreams    The upstream APIs the app calls, by name (#305), each URL and token
     *                                      read from the environment here.
     * @param OidcProviders   $oidc         The OpenID Connect providers people sign in through, by name (#313),
     *                                      each client secret read from the environment here.
     * @param BroadcastConfig $broadcast    How broadcast streams use the site's workers (#314).
     * @param Webhooks        $webhooks     The webhooks the app takes, by path (#321), each secret read from the
     *                                      environment here.
     * @param OAuthServer     $oauth        The app as an OAuth 2 authorization server (#331); off with no issuer.
     *
     * @throws InvalidArgumentException When the name is blank or the cap is below one byte.
     */
    public function __construct(
        public string $name,
        public TesseraRelease $tessera,
        public ?string $theme = null,
        public int $maxBodyBytes = self::MAX_BODY_BYTES,
        public Upstreams $upstreams = new Upstreams(),
        public OidcProviders $oidc = new OidcProviders(),
        public BroadcastConfig $broadcast = new BroadcastConfig(),
        public Webhooks $webhooks = new Webhooks(),
        public OAuthServer $oauth = new OAuthServer(),
    ) {
        if (trim($name) === '') {
            throw new InvalidArgumentException('AppConfig: the application name must not be blank.');
        }

        if ($maxBodyBytes < 1) {
            throw new InvalidArgumentException('AppConfig: the request body cap must be at least one byte.');
        }
    }
}
