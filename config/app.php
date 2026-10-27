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
    tessera: new TesseraRelease('0.2.0', [
        'tessera.csp.iife.js' => '388037e729fd87a4eb74ea95581b287cbdab8fe39cbe44c9e9f7003dc2a545d6',
        'tessera.csp.iife.js.map' => '1a45093dcf371c811b39f62a702a60539a6207fdcd73996047b1c126ad04dc23',
    ]),
    theme: 'app',
);
