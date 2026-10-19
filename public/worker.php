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
 * request, a PSR-15 stack answers it (the route errors around the router, which
 * hands each route's handler the request), and the response goes out through
 * the emitter. The routes are in config/routes.php. Input that does not fit the
 * typed field shapes answers 400. The kernel takes this wiring over in #21.
 *
 * Outside FrankenPHP (a lint pass, a plain `php` run) the function does not
 * exist and the file returns without serving anything.
 */

use AIC\Config\Domain\AppConfig;
use AIC\Frankenphp\Contracts\SapiSink;
use AIC\Frankenphp\Domain\SapiRequest;
use AIC\Frankenphp\Infrastructure\SapiRequestExtractor;
use AIC\Frankenphp\Infrastructure\SapiResponseEmitter;
use AIC\Http\Application\ResponseBuilder;
use AIC\Http\Application\ResponseFactory;
use AIC\Http\Application\ServerRequestFactory;
use AIC\Http\Application\StreamFactory;
use AIC\Http\Application\UriFactory;
use AIC\Http\Domain\InputParser;
use AIC\Http\Domain\MalformedInputException;
use AIC\Middleware\Application\MiddlewareStep;
use AIC\Routing\Application\RouteErrorMiddleware;
use AIC\Routing\Application\Router;
use AIC\Routing\Domain\RouteTable;
use App\Http\GreetingPage;
use App\Http\HtmlResponder;
use App\Http\PlaceholderPage;

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

$responses = new ResponseBuilder($responseFactory, $streamFactory);
$htmlResponder = new HtmlResponder($responses, $appConfig);
$stack = new MiddlewareStep(
    new RouteErrorMiddleware($responses),
    new Router($routes, [
        GreetingPage::class => new GreetingPage($htmlResponder),
        PlaceholderPage::class => new PlaceholderPage($htmlResponder, $appConfig, $bootedAt),
    ]),
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

$serve = static function () use ($extractor, $emitter, $responses, $stack, $sapiRequest): void {
    try {
        $response = $stack->handle($extractor->extract($sapiRequest()));
    } catch (MalformedInputException $malformedInputException) {
        $response = $responses->create(
            400,
            'text/html; charset=utf-8',
            '<!doctype html><title>Bad Request</title><h1>Bad Request</h1><p id="error">'
            . htmlspecialchars($malformedInputException->getMessage()) . '</p>',
        );
    }

    $emitter->emit($response);
};

while (frankenphp_handle_request($serve)) {
    // Serve the next request until FrankenPHP stops this worker.
}
