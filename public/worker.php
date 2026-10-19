<?php

declare(strict_types=1);

/*
 * worker.php: the FrankenPHP worker entry point.
 *
 * FrankenPHP boots this file once per worker, then calls the handler once per
 * request through frankenphp_handle_request() until the worker is told to stop.
 * At boot it reads the app's config (config/app.php, a typed AppConfig). Until
 * the kernel arrives (#21), the handler answers every path with a placeholder
 * page. The page shows when this worker booted: two requests that show the same
 * time were served by one long-lived worker, not a boot per request.
 *
 * Outside FrankenPHP (a lint pass, a plain `php` run) the function does not
 * exist and the file returns without serving anything.
 */

use AIC\Config\Domain\AppConfig;

if (!function_exists('frankenphp_handle_request')) {
    return;
}

require __DIR__ . '/../vendor/autoload.php';

$appConfig = require __DIR__ . '/../config/app.php';

if (!$appConfig instanceof AppConfig) {
    throw new RuntimeException('config/app.php must return an ' . AppConfig::class . '.');
}

$appName = htmlspecialchars($appConfig->name);
$bootedAt = gmdate('Y-m-d\\TH:i:s\\Z');

$serve = static function () use ($appName, $bootedAt): void {
    $workerMode = array_key_exists('FRANKENPHP_WORKER', $_SERVER)
        ? 'yes'
        : 'no';

    http_response_code(200);
    header('Content-Type: text/html; charset=utf-8');
    echo <<<HTML
        <!doctype html>
        <html lang="en">
        <head>
        <meta charset="utf-8">
        <title>{$appName}</title>
        </head>
        <body>
        <h1>{$appName}</h1>
        <p>The FrankenPHP worker is serving. The kernel arrives with #21.</p>
        <dl>
        <dt>App name (config/app.php)</dt><dd id="app-name">{$appName}</dd>
        <dt>Worker mode</dt><dd id="worker-mode">{$workerMode}</dd>
        <dt>Worker booted</dt><dd id="worker-booted">{$bootedAt}</dd>
        </dl>
        </body>
        </html>
        HTML;
};

while (frankenphp_handle_request($serve)) {
    // Serve the next request until FrankenPHP stops this worker.
}
