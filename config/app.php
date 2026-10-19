<?php

declare(strict_types=1);

/*
 * The application's settings. Each config file returns one typed config
 * object; the worker requires this one once, at boot.
 */

use AIC\Config\Domain\AppConfig;

return new AppConfig(
    name: 'AIC nextgen',
);
