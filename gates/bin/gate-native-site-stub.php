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
 *
 * It is also the fixture's OpenID Connect provider (#313), signing everyone
 * in as `ada` with no screen of its own:
 *
 *   GET  /oidc/authorize  302 back to the one registered redirect URI, the fixture's
 *                         /sign-in/oidc/callback on a loopback port, with a fresh code
 *                         and the state; 400 for any other redirect URI or a state
 *                         that is not hex
 *   POST /oidc/token      the id token for a code, when the PKCE verifier and the
 *                         redirect URI match what the authorization asked; 400 otherwise
 *
 * A code names the authorization's grant, kept in the temporary directory until
 * the token endpoint takes it. The location is rebuilt from the port and the
 * state's hex digits, never echoed from the request.
 */

$server = static function (string $name): string {
    $value = $_SERVER[$name] ?? '';

    if (!is_string($value)) {
        return '';
    }

    return $value;
};
$uri = $server('REQUEST_URI');
$path = strtok($uri, '?');
$encode = static fn (string $text): string => rtrim(strtr(base64_encode($text), '+/', '-_'), '=');
$query = static function (string $name): string {
    $value = $_GET[$name] ?? '';

    return is_string($value)
        ? $value
        : '';
};
$posted = static function (string $name): string {
    $value = $_POST[$name] ?? '';

    return is_string($value)
        ? $value
        : '';
};
$hex = static function (string $text): string {
    if (preg_match('/^(?:[0-9a-f]{8})+$/', $text) !== 1) {
        return '';
    }

    $digits = '';

    foreach (str_split($text, 8) as $chunk) {
        $digits .= sprintf('%08x', (int) hexdec($chunk));
    }

    return $digits;
};
$grantFile = static fn (string $code): string => sys_get_temp_dir() . '/aic-oidc-stub-' . $code . '.json';

if ($path === '/oidc/authorize') {
    $redirect = $query('redirect_uri');
    $state = $hex($query('state'));

    if (preg_match('#^http://127\\.0\\.0\\.1:\\d+/sign-in/oidc/callback$#', $redirect) !== 1 || $state === '') {
        http_response_code(400);

        return;
    }

    $code = bin2hex(random_bytes(16));
    file_put_contents($grantFile($code), (string) json_encode([
        'challenge' => $query('code_challenge'),
        'client' => $query('client_id'),
        'nonce' => $query('nonce'),
        'redirect' => $redirect,
    ]));
    $port = (int) substr($redirect, strlen('http://127.0.0.1:'));
    $location = 'http://127.0.0.1:' . $port . '/sign-in/oidc/callback?code=' . $code . '&state=' . $state;
    header('Location: ' . $location, true, 302);

    return;
}

if ($path === '/oidc/token') {
    $code = $hex($posted('code'));
    $kept = $code === '' || !is_file($grantFile($code))
        ? ''
        : (string) file_get_contents($grantFile($code));

    if ($code !== '' && is_file($grantFile($code))) {
        unlink($grantFile($code));
    }

    $grants = [json_decode($kept, true)];
    $grant = is_array($grants[0])
        ? $grants[0]
        : [];
    $matches = $grant !== []
        && $posted('grant_type') === 'authorization_code'
        && $posted('client_id') === ($grant['client'] ?? null)
        && $posted('redirect_uri') === ($grant['redirect'] ?? null)
        && $encode(hash('sha256', $posted('code_verifier'), true)) === ($grant['challenge'] ?? null);
    header('Content-Type: application/json');

    if (!$matches) {
        http_response_code(400);
        echo '{"error":"invalid_grant"}';

        return;
    }

    $claims = [
        'aud' => $grant['client'] ?? '',
        'email' => 'ada@example.test',
        'exp' => time() + 300,
        'iat' => time(),
        'iss' => 'http://' . $server('HTTP_HOST'),
        'nonce' => $grant['nonce'] ?? '',
        'sub' => 'ada',
    ];
    $idToken = $encode('{"alg":"none"}') . '.' . $encode((string) json_encode($claims)) . '.';
    echo json_encode(['access_token' => 'stub-access', 'token_type' => 'Bearer', 'expires_in' => 300, 'id_token' => $idToken]);

    return;
}

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
