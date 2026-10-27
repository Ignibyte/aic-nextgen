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
    tessera: new TesseraRelease('0.1.1', [
        'tessera.csp.iife.js' => '23f45d9ea928f34940d464df949686c398003d173c56b337a4c49c52849b1d98',
        'tessera.csp.iife.js.map' => '94711f125575cdcee702dcbfdb3ad5e9e2b201393cfd4eb423ab79c4bf17a2d9',
    ]),
    theme: 'app',
);
