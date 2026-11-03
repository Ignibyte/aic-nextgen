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
 */

use AIC\Config\Domain\AppConfig;
use AIC\Tessera\Domain\TesseraRelease;

return new AppConfig(
    name: 'AIC nextgen',
    tessera: new TesseraRelease('0.4.2', [
        'tessera.csp.iife.js' => '95f7c2c63afd0a48b66cfa9dd524f21a6b2f74e8676586864d37a99c450c9527',
        'tessera.csp.iife.js.map' => '8b751b5ab2d758af64f0d55c4c2fdfe53c6b92f8664914eec8a3d111bc9ea317',
    ]),
    theme: 'app',
    maxBodyBytes: 16_384,
);
