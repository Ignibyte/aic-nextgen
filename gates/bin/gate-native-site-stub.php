<?php

declare(strict_types=1);

/*
 * gate:23's stub upstream (#305): a router for `php -S`, which the gate runs
 * beside the two sites and names in AIC_UPSTREAM_STUB, so the fixture's
 * /upstream calls something local. Each answer is JSON and fixed, so PHP and
 * the compiled site get the same:
 *
 *   GET /echo        200: the method, and whether the bearer token is `stub-token`
 *   GET /status/201  201
 *   GET /status/503  503
 *   GET /slow        200 after half a second, longer than the fixture lets it take
 */

$uri = $_SERVER['REQUEST_URI'] ?? '/';
$signed = ($_SERVER['HTTP_AUTHORIZATION'] ?? '') === 'Bearer stub-token';
$get = ($_SERVER['REQUEST_METHOD'] ?? '') === 'GET';
$status = match ($uri) {
    '/echo', '/slow' => 200,
    '/status/201' => 201,
    '/status/503' => 503,
    default => 404,
};

if ($uri === '/slow') {
    usleep(500_000);
}

http_response_code($status);
header('Content-Type: application/json');

echo match ($uri) {
    '/echo' => sprintf('{"authorized":%s,"method":"%s"}', $signed ? 'true' : 'false', $get ? 'GET' : 'other'),
    '/slow' => '{"slow":true}',
    default => sprintf('{"status":%d}', $status),
};
