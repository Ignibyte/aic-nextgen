<?php

declare(strict_types=1);

/*
 * The application's settings. Each config file returns one typed config
 * object; the worker requires this one once, at boot.
 *
 * The Tessera pin: the release and the SHA-256 of each file the build copies
 * into build/assets/, from the release's SHA256SUMS. Upgrading is a new
 * version and new sums here, then `composer run build`.
 *
 * The theme: the folder under themes/ whose components, layouts and assets the
 * build compiles and publishes. The body cap: a larger request body answers 413
 * before the session.
 */

use AIC\Config\Domain\AppConfig;
use AIC\Tessera\Domain\TesseraRelease;

return new AppConfig(
    name: 'AIC app',
    tessera: new TesseraRelease('0.4.3', [
        'tessera.csp.iife.js' => 'b08e446935ab6d2177935fc79a939afbd6843ed3fc6eca6f57acc812910fcdd4',
        'tessera.csp.iife.js.map' => 'a9c4fb509fe2cb5a1671cd7655bfe893e14d91b5db7987fd98a92d7a8a392f8a',
    ]),
    theme: 'app',
);
