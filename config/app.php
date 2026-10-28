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
    tessera: new TesseraRelease('0.3.0', [
        'tessera.csp.iife.js' => '9566544fa6ae89466b61d74098e43961627b3463f2bb75e90454711266651180',
        'tessera.csp.iife.js.map' => 'd83e7c9ac70cf1b67ede63a20e5f63ded37402a16dfeee8c47ba3cf40e0a041f',
    ]),
    theme: 'app',
);
