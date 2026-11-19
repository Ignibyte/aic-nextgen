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
 */

use AIC\Auth\Domain\OidcProvider;
use AIC\Auth\Domain\OidcProviders;
use AIC\Config\Domain\AppConfig;
use AIC\HttpClient\Domain\Upstream;
use AIC\HttpClient\Domain\Upstreams;
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
    tessera: new TesseraRelease('0.4.3', [
        'tessera.csp.iife.js' => 'b08e446935ab6d2177935fc79a939afbd6843ed3fc6eca6f57acc812910fcdd4',
        'tessera.csp.iife.js.map' => 'a9c4fb509fe2cb5a1671cd7655bfe893e14d91b5db7987fd98a92d7a8a392f8a',
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
            clientId: 'aic-fixture',
            clientSecret: $setting('AIC_OIDC_STUB_SECRET', ''),
            callback: '/sign-in/oidc/callback',
            scopes: 'openid email',
        ),
    ]),
);
