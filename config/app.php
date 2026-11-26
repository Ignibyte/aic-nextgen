<?php

declare(strict_types=1);

/*
 * The application's settings. Each config file returns one typed config
 * object; the worker requires this one once, at boot.
 *
 * The Tessera pin (#70): the release and the SHA-256 of each file the build
 * copies into public/assets/tessera-VERSION/, from the release's SHA256SUMS.
 * Upgrading is a new version and new sums here, then `composer run build`.
 *
 * The theme (#153): the folder under themes/ whose components, layouts and
 * assets the build compiles and publishes.
 *
 * The request body cap (#196): a larger body answers 413 before the session.
 * The fixture keeps it small, so gate:23 can send a body over it.
 *
 * The upstreams (#305): each API the app calls, by name, its URL and token
 * from the environment. `stub` is gate:23's local stub server, which the gate
 * names in AIC_UPSTREAM_STUB; elsewhere nothing answers there.
 *
 * The sign-in providers (#313): `stub` is the same stub as an OpenID Connect
 * provider, a public client with no secret, its callback on whichever origin
 * the request came to.
 *
 * Broadcast streams (#314): the fixture holds each connection 3 seconds, so
 * gate:23's stream step ends soon and the feed page shows a reconnect
 * often.
 *
 * The webhooks (#321): each path a sender posts signed deliveries to, its
 * signature header and its secret from the environment. gate:23 names the
 * fixture's secret in AIC_WEBHOOK_STUB_SECRET and signs its deliveries with
 * it; without it every delivery answers 401.
 *
 * The authorization server (#331): the site's own origin as the issuer, so
 * /oauth/token and the two metadata documents answer, and a route's need
 * refused to a visitor names the protected resource's metadata. The account
 * module's /oauth/authorize is the consent page.
 */

use AIC\Auth\Domain\OidcProvider;
use AIC\Auth\Domain\OidcProviders;
use AIC\Broadcast\Domain\BroadcastConfig;
use AIC\Config\Domain\AppConfig;
use AIC\Http\Domain\Webhook;
use AIC\Http\Domain\Webhooks;
use AIC\HttpClient\Domain\Upstream;
use AIC\HttpClient\Domain\Upstreams;
use AIC\OAuth\Domain\OAuthServer;
use AIC\Tessera\Domain\TesseraRelease;

$setting = static function (string $name, string $default): string {
    $value = getenv($name);

    return is_string($value) && $value !== ''
        ? $value
        : $default;
};

$stub = $setting('AIC_UPSTREAM_STUB', 'http://127.0.0.1:8095');

return new AppConfig(
    name: 'AIC nextgen',
    tessera: new TesseraRelease('0.4.6', [
        'tessera.csp.iife.js' => 'f7b238d7538f5a5952df25d319f119b3c40f89aa4d6ef37abf8814b575932cca',
        'tessera.csp.iife.js.map' => '92e2d0ae35296c6697eadc7fd5184c0ce739e6d6bcb130eac487e438f8e10999',
    ]),
    theme: 'app',
    maxBodyBytes: 16_384,
    upstreams: new Upstreams([
        'stub' => new Upstream($stub, $setting('AIC_UPSTREAM_STUB_TOKEN', '')),
    ]),
    oidc: new OidcProviders([
        'stub' => new OidcProvider(
            issuer: $stub,
            authorizationEndpoint: $stub . '/oidc/authorize',
            tokenEndpoint: $stub . '/oidc/token',
            jwksUri: $stub . '/oidc/jwks',
            clientId: 'aic-fixture',
            clientSecret: $setting('AIC_OIDC_STUB_SECRET', ''),
            callback: '/sign-in/oidc/callback',
            scopes: 'openid email',
            algorithms: ['RS256', 'ES256'],
        ),
    ]),
    broadcast: new BroadcastConfig(holdSeconds: 3),
    webhooks: new Webhooks([
        '/scaffold/delivery' => new Webhook('X-Signature', $setting('AIC_WEBHOOK_STUB_SECRET', '')),
    ]),
    oauth: new OAuthServer('https://aic-nextgen.ddev.site:8843'),
);
