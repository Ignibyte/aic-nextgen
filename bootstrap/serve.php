<?php

declare(strict_types=1);

/*
 * serve.php: serve one HTTP request.
 *
 * The same file runs interpreted (public/index.php under php-fpm or `php -S`)
 * and compiled (the class bundle and this file built with `elephc --web`, see
 * gate:23). Either way it runs once per request with fresh state, as PHP-FPM
 * does: it reads the app's config (config/app.php, config/routes.php and
 * config/database.php, typed), wires the framework's SAPI services and the
 * app's composition root (App\AppServices), narrows the superglobals into a
 * SapiRequest, answers it through the kernel and emits the response through
 * PhpSapiSink (header(), http_response_code() and echo). This is the one place
 * that reads the superglobals.
 */

use AIC\Config\Domain\AppConfig;
use AIC\Database\Domain\DatabaseConfig;
use AIC\Database\Infrastructure\PdoDatabase;
use AIC\Kernel\Infrastructure\SapiServices;
use AIC\Routing\Domain\RouteTable;
use AIC\Sapi\Contracts\SapiRequest;
use AIC\Sapi\Infrastructure\PhpSapiSink;
use App\AppServices;

$appConfig = require __DIR__ . '/../config/app.php';

if (!$appConfig instanceof AppConfig) {
    throw new RuntimeException('config/app.php must return an ' . AppConfig::class . '.');
}

$routes = require __DIR__ . '/../config/routes.php';

if (!$routes instanceof RouteTable) {
    throw new RuntimeException('config/routes.php must return an ' . RouteTable::class . '.');
}

$databaseConfig = require __DIR__ . '/../config/database.php';

if (!$databaseConfig instanceof DatabaseConfig) {
    throw new RuntimeException('config/database.php must return an ' . DatabaseConfig::class . '.');
}

$sapiServices = new SapiServices(new PhpSapiSink());
$appServices = new AppServices(
    $appConfig,
    $routes,
    $sapiServices->requestExtractor(),
    $sapiServices->responseBuilder(),
    gmdate('Y-m-d\\TH:i:s\\Z'),
    __DIR__ . '/..',
    new PdoDatabase($databaseConfig),
);

$server = [];

foreach ($_SERVER as $key => $value) {
    if (!is_string($key) || !is_scalar($value)) {
        continue;
    }

    $server[$key] = $value;
}

$body = file_get_contents('php://input');

$sapiServices->sapiResponseEmitter()->emit(
    $appServices->kernel()->serve(new SapiRequest($server, $body === false ? '' : $body)),
);
