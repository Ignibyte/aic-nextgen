<?php

declare(strict_types=1);

/*
 * index.php: the interpreted entry point, for php-fpm (DDEV) and `php -S`
 * (gate:23's reference). It loads the autoloader and serves the request; the
 * compiled site replaces the autoloader with the class bundle.
 */

require __DIR__ . '/../vendor/autoload.php';

require __DIR__ . '/../bootstrap/serve.php';
