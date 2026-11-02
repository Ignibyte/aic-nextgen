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
    tessera: new TesseraRelease('0.4.0', [
        'tessera.csp.iife.js' => '9c1da8d63b651bf86b1c3a48e19ff529227a6e1bebec3da64c7efcf6f2271fde',
        'tessera.csp.iife.js.map' => 'c0ccc27775b90a42344b8a2ab62b7f417cbb092255eea3763e26eee7ed2c368b',
    ]),
    theme: 'app',
    maxBodyBytes: 16_384,
);
