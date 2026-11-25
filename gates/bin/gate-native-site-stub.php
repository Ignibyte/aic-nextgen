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
 *                         redirect URI match what the authorization asked; 400 otherwise.
 *                         It is signed RS256 by the stub's RSA key (#343)
 *   GET  /oidc/jwks       the stub's two public keys, `stub-rsa` (RS256) and `stub-ec`
 *                         (ES256 on P-256), as a JSON Web Key Set
 *   POST /oidc/next       mode=es256, tampered or none: the next id token is signed
 *                         ES256, has its payload changed after signing, or is unsigned
 *
 * The keys are made on first use for each port the stub serves on and kept in
 * the temporary directory, so no private key is in the repository.
 *
 * A code names the authorization's grant, kept in the temporary directory until
 * the token endpoint takes it. The location is rebuilt from the port and the
 * state's hex digits, never echoed from the request.
 */

namespace Gates\Bin;

use function base64_encode;
use function bin2hex;
use function fclose;
use function file_get_contents;
use function file_put_contents;
use function flock;
use function fopen;
use function fwrite;
use function hash;
use function header;
use function hexdec;
use function http_response_code;
use function in_array;
use function is_array;
use function is_file;
use function is_string;
use function json_decode;
use function json_encode;
use function ltrim;
use function openssl_pkey_export;
use function openssl_pkey_get_details;
use function openssl_pkey_get_private;
use function openssl_pkey_new;
use function openssl_sign;
use function ord;
use function preg_match;
use function random_bytes;
use function rtrim;
use function sprintf;
use function str_pad;
use function str_split;
use function stream_get_contents;
use function strlen;
use function strtok;
use function strtr;
use function substr;
use function sys_get_temp_dir;
use function time;
use function unlink;
use function usleep;

use const LOCK_EX;
use const LOCK_UN;
use const OPENSSL_ALGO_SHA256;
use const OPENSSL_KEYTYPE_EC;
use const OPENSSL_KEYTYPE_RSA;
use const STR_PAD_LEFT;

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

$keyFile = sys_get_temp_dir() . '/aic-oidc-stub-keys-' . $server('SERVER_PORT') . '.json';
$nextFile = sys_get_temp_dir() . '/aic-oidc-stub-next-' . $server('SERVER_PORT');
$madeKeys = static function (): string {
    $options = [
        'rsa' => ['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2_048],
        'ec' => ['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1'],
    ];
    $keys = [];

    foreach ($options as $name => $option) {
        $made = openssl_pkey_new($option);
        $exported = '';

        if ($made === false || !openssl_pkey_export($made, $exported)) {
            continue;
        }

        $keys[$name] = $exported;
    }

    return (string) json_encode($keys);
};
$keyStore = static function () use ($keyFile, $madeKeys): string {
    $handle = fopen($keyFile, 'c+');

    if ($handle === false) {
        return '';
    }

    flock($handle, LOCK_EX);
    $json = (string) stream_get_contents($handle);

    if ($json === '') {
        $json = $madeKeys();
        fwrite($handle, $json);
    }

    flock($handle, LOCK_UN);
    fclose($handle);

    return $json;
};
$privateKey = static function (string $name) use ($keyStore): string {
    $decoded = [json_decode($keyStore(), true)];
    $keys = is_array($decoded[0])
        ? $decoded[0]
        : [];
    $pem = [$keys[$name] ?? null];

    return is_string($pem[0])
        ? $pem[0]
        : '';
};
$component = static function (string $pem, string $group, string $name): string {
    $key = openssl_pkey_get_private($pem);
    $details = $key === false
        ? false
        : openssl_pkey_get_details($key);
    $values = [is_array($details) ? $details[$group] ?? null : null];
    $members = is_array($values[0])
        ? $values[0]
        : [];
    $value = [$members[$name] ?? null];

    return is_string($value[0])
        ? $value[0]
        : '';
};
$publicJwk = static function (string $name) use ($encode, $component, $privateKey): string {
    $pem = $privateKey($name);
    $coordinate = static fn (string $axis): string => $encode(str_pad($component($pem, 'ec', $axis), 32, "\0", STR_PAD_LEFT));
    $rsa = static fn (string $part): string => $encode($component($pem, 'rsa', $part));
    $jwk = $name === 'rsa'
        ? ['alg' => 'RS256', 'e' => $rsa('e'), 'kid' => 'stub-rsa', 'kty' => 'RSA', 'n' => $rsa('n')]
        : ['alg' => 'ES256', 'crv' => 'P-256', 'kid' => 'stub-ec', 'kty' => 'EC', 'x' => $coordinate('x'), 'y' => $coordinate('y')];

    return (string) json_encode($jwk + ['use' => 'sig']);
};
$sign = static function (string $input, string $pem): string {
    $signature = '';
    openssl_sign($input, $signature, $pem, OPENSSL_ALGO_SHA256);
    $text = static fn (mixed $bytes): string => is_string($bytes)
        ? $bytes
        : '';

    return $text($signature);
};
$rawEcdsa = static function (string $der): string {
    $rLength = ord($der[3]);
    $fixed = static fn (string $number): string => str_pad(ltrim($number, "\0"), 32, "\0", STR_PAD_LEFT);

    return $fixed(substr($der, 4, $rLength)) . $fixed(substr($der, 6 + $rLength, ord($der[5 + $rLength])));
};

if ($path === '/oidc/jwks') {
    header('Content-Type: application/json');
    echo '{"keys":[' . $publicJwk('rsa') . ',' . $publicJwk('ec') . ']}';

    return;
}

if ($path === '/oidc/next') {
    $mode = $posted('mode');

    if (in_array($mode, ['es256', 'tampered', 'none'], true)) {
        file_put_contents($nextFile, $mode);
    }

    http_response_code(204);

    return;
}

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
    $mode = is_file($nextFile)
        ? (string) file_get_contents($nextFile)
        : 'rs256';

    if (is_file($nextFile)) {
        unlink($nextFile);
    }

    $header = match ($mode) {
        'none' => ['alg' => 'none'],
        'es256' => ['alg' => 'ES256', 'kid' => 'stub-ec', 'typ' => 'JWT'],
        default => ['alg' => 'RS256', 'kid' => 'stub-rsa', 'typ' => 'JWT'],
    };
    $input = $encode((string) json_encode($header)) . '.' . $encode((string) json_encode($claims));
    $signature = $mode === 'none'
        ? ''
        : $sign($input, $privateKey($mode === 'es256' ? 'ec' : 'rsa'));

    if ($mode === 'tampered') {
        $input = $encode((string) json_encode($header)) . '.' . $encode((string) json_encode(['sub' => 'eve'] + $claims));
    }

    $idToken = $input . '.' . $encode($mode === 'es256' ? $rawEcdsa($signature) : $signature);
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
