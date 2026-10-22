<?php

declare(strict_types=1);

/*
 * serve.php: serve one HTTP request.
 *
 * The same file runs interpreted (public/index.php under php-fpm or `php -S`)
 * and compiled (the class bundle and this file built with `elephc --web`, see
 * gate:23). Either way it runs once per request with fresh state, as PHP-FPM
 * does: it reads the app's config (config/app.php, config/database.php and
 * config/secret.php, typed), wires the framework's SAPI services and the app
 * kernel (AIC\Module\Application\AppKernel) over the generated wiring
 * (App\Generated\WebModules, #93), narrows the superglobals into a
 * SapiRequest, answers it through the kernel and emits the response through
 * PhpSapiSink (header(), http_response_code() and echo). This is the one place
 * that reads the superglobals.
 */

use AIC\Config\Domain\AppConfig;
use AIC\Database\Domain\DatabaseConfig;
use AIC\Database\Infrastructure\PdoDatabase;
use AIC\Kernel\Infrastructure\SapiServices;
use AIC\Module\Application\AppKernel;
use AIC\Module\Application\HandlerContexts;
use AIC\Sapi\Contracts\SapiRequest;
use AIC\Sapi\Contracts\SapiUpload;
use AIC\Sapi\Infrastructure\PhpSapiSink;
use AIC\Sapi\Infrastructure\SapiInput;
use AIC\Secret\Domain\AppSecret;
use App\Generated\WebModules;

try {
    $appConfig = require __DIR__ . '/../config/app.php';

    if (!$appConfig instanceof AppConfig) {
        throw new RuntimeException('config/app.php must return an ' . AppConfig::class . '.');
    }

    $databaseConfig = require __DIR__ . '/../config/database.php';

    if (!$databaseConfig instanceof DatabaseConfig) {
        throw new RuntimeException('config/database.php must return an ' . DatabaseConfig::class . '.');
    }

    $appSecret = require __DIR__ . '/../config/secret.php';

    if (!$appSecret instanceof AppSecret) {
        throw new RuntimeException('config/secret.php must return an ' . AppSecret::class . '.');
    }

    $sapiServices = new SapiServices(new PhpSapiSink());
    $database = new PdoDatabase($databaseConfig);
    $appKernel = new AppKernel(
        new HandlerContexts(
            $appConfig,
            $sapiServices->responseBuilder(),
            $database,
            $appSecret,
            gmdate('Y-m-d\\TH:i:s\\Z'),
            __DIR__ . '/..',
        ),
        $sapiServices->requestExtractor(),
        $sapiServices->responseBuilder(),
        $database,
        new WebModules(),
    );
} catch (Throwable) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "The application could not start. Check its configuration.\n";

    return;
}

$sapiInput = new SapiInput();
$server = [];

foreach ($_SERVER as $key => $value) {
    $name = $sapiInput->name($key);

    if ($name === null || !is_scalar($value)) {
        continue;
    }

    $server[$name] = $value;
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

foreach (array_filter($_FILES, is_array(...)) as $field => $file) {
    $upload = $sapiInput->upload($field, array_filter($file, is_scalar(...)));

    if (!($upload instanceof SapiUpload)) {
        continue;
    }

    $uploads[] = $upload;
}

$sapiServices->sapiResponseEmitter()->emit(
    $appKernel->kernel()->serve(new SapiRequest(
        $server,
        $body === false ? '' : $body,
        $fields,
        $uploads,
    )),
);
