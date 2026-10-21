<?php

declare(strict_types=1);

/*
 * serve.php: serve one HTTP request.
 *
 * The same file runs interpreted (public/index.php under php-fpm or `php -S`)
 * and compiled (the class bundle and this file built with `elephc --web`, see
 * gate:23). Either way it runs once per request with fresh state, as PHP-FPM
 * does: it reads the app's config (config/app.php, config/routes.php,
 * config/database.php and config/secret.php, typed), adds the modules' routes
 * (App\Generated\ModuleRoutes, #92) to config/routes.php's, wires the framework's SAPI services and the
 * app's composition root (App\AppServices), narrows the superglobals into a
 * SapiRequest, answers it through the kernel and emits the response through
 * PhpSapiSink (header(), http_response_code() and echo). This is the one place
 * that reads the superglobals.
 */

use AIC\Config\Domain\AppConfig;
use AIC\Database\Domain\DatabaseConfig;
use AIC\Database\Infrastructure\PdoDatabase;
use AIC\Kernel\Infrastructure\SapiServices;
use AIC\Routing\Application\RouteCollector;
use AIC\Sapi\Contracts\SapiRequest;
use AIC\Sapi\Contracts\SapiUpload;
use AIC\Sapi\Infrastructure\PhpSapiSink;
use AIC\Secret\Domain\AppSecret;
use App\AppServices;
use App\Generated\ModuleRoutes;

$appConfig = require __DIR__ . '/../config/app.php';

if (!$appConfig instanceof AppConfig) {
    throw new RuntimeException('config/app.php must return an ' . AppConfig::class . '.');
}

$routeCollector = require __DIR__ . '/../config/routes.php';

if (!$routeCollector instanceof RouteCollector) {
    throw new RuntimeException('config/routes.php must return a ' . RouteCollector::class . '.');
}

$routes = (new ModuleRoutes())->add($routeCollector)->table();

$databaseConfig = require __DIR__ . '/../config/database.php';

if (!$databaseConfig instanceof DatabaseConfig) {
    throw new RuntimeException('config/database.php must return an ' . DatabaseConfig::class . '.');
}

$appSecret = require __DIR__ . '/../config/secret.php';

if (!$appSecret instanceof AppSecret) {
    throw new RuntimeException('config/secret.php must return an ' . AppSecret::class . '.');
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
    $appSecret,
);

$server = [];

foreach ($_SERVER as $key => $value) {
    if (!is_string($key) || !is_scalar($value)) {
        continue;
    }

    $server[$key] = $value;
}

$body = file_get_contents('php://input');

// A multipart post's fields and files, which PHP-FPM does not leave in the body (#44):
// string fields only, and one file per field.
$fields = [];

foreach ($_POST as $name => $value) {
    if (!is_string($name) || !is_string($value)) {
        continue;
    }

    $fields[$name] = $value;
}

$uploads = [];

foreach ($_FILES as $field => $file) {
    if (!is_string($field) || !is_array($file)) {
        continue;
    }

    $clientName = $file['name'] ?? '';
    $clientType = $file['type'] ?? '';
    $path = $file['tmp_name'] ?? '';
    $error = $file['error'] ?? 4;
    $size = $file['size'] ?? 0;
    $uploads[] = new SapiUpload(
        $field,
        is_string($clientName) ? $clientName : '',
        is_string($clientType) ? $clientType : '',
        is_string($path) ? $path : '',
        is_int($error) ? $error : 4,
        is_int($size) ? $size : 0,
    );
}

$sapiServices->sapiResponseEmitter()->emit(
    $appServices->kernel()->serve(new SapiRequest(
        $server,
        $body === false ? '' : $body,
        $fields,
        $uploads,
    )),
);
