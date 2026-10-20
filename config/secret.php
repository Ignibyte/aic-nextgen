<?php

declare(strict_types=1);

/*
 * The app secret, from the environment only (#43): AIC_APP_SECRET, 32 bytes
 * or more. It never lives in a file in the repository; on a dev box DDEV's
 * ignored .ddev/config.local.yaml sets it, and gate:23 makes one per run.
 */

use AIC\Secret\Domain\AppSecret;

$secret = getenv('AIC_APP_SECRET');

return new AppSecret(is_string($secret) ? $secret : '');
