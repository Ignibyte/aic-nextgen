<?php

declare(strict_types=1);

use Gates\Application\BrowserLook;
use Gates\Application\BrowserTests;
use Gates\Application\GateUser;
use Gates\Application\NativeSiteAnchors;
use Gates\Application\NativeSiteBrowser;
use Gates\Application\NativeSiteBuilder;
use Gates\Application\NativeSiteComparer;
use Gates\Application\NativeSiteGate;
use Gates\Application\NativeSiteServe;
use Gates\Application\NativeToolchain;
use Gates\Infrastructure\GatesPackage;
use Gates\Infrastructure\LocalNativeSiteSetup;
use Gates\Infrastructure\ProcServerLauncher;
use Gates\Infrastructure\ProjectRoot;
use Gates\Infrastructure\StreamHttpClient;
use Gates\Infrastructure\SystemProcessRunner;

require __DIR__ . '/project.php';

/*
 * gate:23 — the native site; with `serve [port]`, `composer run native:serve`;
 * with `browser [--base-url=<url>] [<spec>…]`, `vendor/bin/aic-gate browser` (#174).
 *
 * The environment is read here, at the boundary: ELEPHC_BIN overrides the
 * fork's in-place binary, the compiler gets gate:21's caches, and the database
 * is the PG* environment or DDEV's published port. Serve mode builds the
 * compiled site and runs it on 127.0.0.1 (port 8090 by default) until
 * interrupted, for a browser.
 */
$root = (new ProjectRoot())->path();
$elephc = getenv('ELEPHC_BIN');
$env = getenv();
$env['XDG_CACHE_HOME'] = $root . '/.native-gate/.cache';
$nativeCache = getenv('ELEPHC_NATIVE_CACHE');
$env['ELEPHC_NATIVE_CACHE'] = is_string($nativeCache) && $nativeCache !== ''
    ? $nativeCache
    : '/mnt/fast/cache/elephc-native';

$processRunner = new SystemProcessRunner();
$toolchain = new NativeToolchain(
    $processRunner,
    is_string($elephc) && $elephc !== '' ? $elephc : '/srv/stacks/elephc/target/release/elephc',
    $env,
);
$setup = (new LocalNativeSiteSetup($processRunner))->read($env, $root);

$package = (new GatesPackage())->path();
$builder = new NativeSiteBuilder($toolchain, $package);
$browserTests = new BrowserTests($processRunner, $package);
$gateUser = new GateUser($processRunner, $toolchain->phpPath());

$arguments = $argv ?? [];

if (($arguments[1] ?? '') === 'browser') {
    exit((new BrowserLook($browserTests, $gateUser))->run($root, $setup, array_slice($arguments, 2)));
}

if (($arguments[1] ?? '') !== 'serve') {
    $gate = new NativeSiteGate(
        $toolchain,
        $builder,
        new ProcServerLauncher(),
        new NativeSiteComparer(new StreamHttpClient(), new NativeSiteAnchors(new StreamHttpClient())),
        $gateUser,
        new NativeSiteBrowser($browserTests),
    );

    exit($gate->run($root, $setup));
}

$port = $arguments[2] ?? '';

exit((new NativeSiteServe($builder))->serve($root, $setup, preg_match('/^\\d+$/', $port) === 1 ? $port : '8090'));
