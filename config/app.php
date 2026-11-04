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
    tessera: new TesseraRelease('0.4.3', [
        'tessera.csp.iife.js' => 'b08e446935ab6d2177935fc79a939afbd6843ed3fc6eca6f57acc812910fcdd4',
        'tessera.csp.iife.js.map' => 'a9c4fb509fe2cb5a1671cd7655bfe893e14d91b5db7987fd98a92d7a8a392f8a',
    ]),
    theme: 'app',
    maxBodyBytes: 16_384,
);
