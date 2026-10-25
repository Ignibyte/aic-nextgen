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
 */

use AIC\Config\Domain\AppConfig;
use AIC\Tessera\Domain\TesseraRelease;

return new AppConfig(
    name: 'AIC nextgen',
    tessera: new TesseraRelease('0.1.0', [
        'tessera.csp.iife.js' => 'c200d65b0c2ead2ba9b1467249b5b245eda6f65f48c7072252ee23bb3a2c646b',
        'tessera.csp.iife.js.map' => '55ac49ce2c4ef4aa2aa6233669686641a6c07eb865898d9b708ab291e4087d56',
    ]),
    theme: 'app',
);
