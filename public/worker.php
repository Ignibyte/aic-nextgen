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
 * request, and the response goes out through the emitter. Until the kernel
 * arrives (#21), every path answers with a placeholder page that shows what the
 * request carried; input that does not fit the typed field shapes answers 400.
 *
 * Outside FrankenPHP (a lint pass, a plain `php` run) the function does not
 * exist and the file returns without serving anything.
 */

use AIC\Config\Domain\AppConfig;
use AIC\Frankenphp\Contracts\SapiSink;
use AIC\Frankenphp\Domain\SapiRequest;
use AIC\Frankenphp\Infrastructure\SapiRequestExtractor;
use AIC\Frankenphp\Infrastructure\SapiResponseEmitter;
use AIC\Http\Application\ResponseFactory;
use AIC\Http\Application\ServerRequestFactory;
use AIC\Http\Application\StreamFactory;
use AIC\Http\Application\UriFactory;
use AIC\Http\Domain\InputParser;
use AIC\Http\Domain\MalformedInputException;
use Psr\Http\Message\ServerRequestInterface;

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

$uriFactory = new UriFactory();
$streamFactory = new StreamFactory();
$responseFactory = new ResponseFactory($streamFactory);
$extractor = new SapiRequestExtractor(
    new ServerRequestFactory($uriFactory, $streamFactory),
    $uriFactory,
    $streamFactory,
    new InputParser(),
);
$emitter = new SapiResponseEmitter(new class () implements SapiSink {
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

/**
 * Render the query fields as list items, one per `name=value` pair as a query
 * string would carry it (`tags[0]=a`, `opt[k]=v`).
 *
 * @param ServerRequestInterface $request The request.
 *
 * @return string The `<li>` items.
 */
$queryItems = static function (ServerRequestInterface $serverRequest): string {
    $encoded = http_build_query($serverRequest->getQueryParams());

    $items = '';

    foreach ($encoded === '' ? [] : explode('&', $encoded) as $pair) {
        $items .= '<li>' . htmlspecialchars(urldecode($pair)) . '</li>';
    }

    return $items;
};

/**
 * Render the placeholder page for a request.
 *
 * @param ServerRequestInterface $request The request.
 *
 * @return string The page.
 */
$page = static function (ServerRequestInterface $serverRequest) use ($appName, $bootedAt, $queryItems): string {
    $workerMode = array_key_exists('FRANKENPHP_WORKER', $serverRequest->getServerParams())
        ? 'yes'
        : 'no';
    $method = htmlspecialchars($serverRequest->getMethod());
    $path = htmlspecialchars($serverRequest->getUri()->getPath());

    return <<<HTML
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
        <dt>Request method</dt><dd id="request-method">{$method}</dd>
        <dt>Request path</dt><dd id="request-path">{$path}</dd>
        </dl>
        <ul id="query">{$queryItems($serverRequest)}</ul>
        </body>
        </html>
        HTML;
};

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

$serve = static function () use ($extractor, $emitter, $responseFactory, $streamFactory, $page, $sapiRequest): void {
    try {
        $status = 200;
        $html = $page($extractor->extract($sapiRequest()));
    } catch (MalformedInputException $malformedInputException) {
        $status = 400;
        $html = '<!doctype html><title>Bad Request</title><h1>Bad Request</h1><p id="error">'
            . htmlspecialchars($malformedInputException->getMessage()) . '</p>';
    }

    $emitter->emit(
        $responseFactory->createResponse($status)
            ->withHeader('Content-Type', 'text/html; charset=utf-8')
            ->withBody($streamFactory->createStream($html)),
    );
};

while (frankenphp_handle_request($serve)) {
    // Serve the next request until FrankenPHP stops this worker.
}
