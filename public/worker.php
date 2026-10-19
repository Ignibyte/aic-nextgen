<?php

declare(strict_types=1);

/*
 * worker.php: the FrankenPHP worker entry point.
 *
 * FrankenPHP boots this file once per worker, then calls the handler once per
 * request through frankenphp_handle_request() until the worker is told to stop.
 * At boot it reads the app's config (config/app.php, a typed AppConfig) and
 * wires the HTTP pieces. Each request is narrowed here, the one place the
 * superglobals are read, into a SapiRequest; the extractor makes the PSR-7
 * request; the kernel (built by the app's composition root, App\AppServices)
 * answers it through the PSR-15 stack, with its 400 and 500 boundaries, and the
 * response goes out through the emitter. The routes are in config/routes.php.
 *
 * Outside FrankenPHP (a lint pass, a plain `php` run) the function does not
 * exist and the file returns without serving anything.
 */

use AIC\Config\Domain\AppConfig;
use AIC\Database\Domain\DatabaseConfig;
use AIC\Database\Infrastructure\PdoDatabase;
use AIC\Frankenphp\Contracts\SapiRequest;
use AIC\Frankenphp\Contracts\SapiSink;
use AIC\Kernel\Infrastructure\SapiServices;
use AIC\Routing\Domain\RouteTable;
use App\AppServices;

if (!function_exists('frankenphp_handle_request')) {
    return;
}

require __DIR__ . '/../vendor/autoload.php';

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

$bootedAt = gmdate('Y-m-d\\TH:i:s\\Z');
$sapiServices = new SapiServices(new class () implements SapiSink {
    /**
     * @param int $code The response status.
     */
    #[Override]
    public function status(int $code): void
    {
        http_response_code($code);
    }

    /**
     * @param string $line    One header line.
     * @param bool   $replace Whether it replaces an earlier header of the same name.
     */
    #[Override]
    public function header(string $line, bool $replace): void
    {
        header($line, $replace);
    }

    /**
     * @param string $bytes Body bytes to send.
     */
    #[Override]
    public function write(string $bytes): void
    {
        echo $bytes;
    }
});
$appServices = new AppServices(
    $appConfig,
    $routes,
    $sapiServices->requestExtractor(),
    $sapiServices->responseBuilder(),
    $bootedAt,
    __DIR__ . '/../resources',
    new PdoDatabase($databaseConfig),
);

/**
 * Narrow this request's superglobals into a SapiRequest: the scalar server params
 * by name, and the raw body.
 *
 * @return SapiRequest The request as the SAPI gave it.
 */
$sapiRequest = static function (): SapiRequest {
    $server = [];

    foreach ($_SERVER as $key => $value) {
        if (!is_string($key) || !is_scalar($value)) {
            continue;
        }

        $server[$key] = $value;
    }

    $body = file_get_contents('php://input');

    return new SapiRequest($server, $body === false ? '' : $body);
};

$serve = static function () use ($sapiServices, $appServices, $sapiRequest): void {
    $sapiServices->sapiResponseEmitter()->emit($appServices->kernel()->serve($sapiRequest()));
};

while (frankenphp_handle_request($serve)) {
    // Serve the next request until FrankenPHP stops this worker.
}
