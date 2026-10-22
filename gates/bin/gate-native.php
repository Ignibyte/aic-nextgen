<?php

declare(strict_types=1);

use Gates\Application\NativeCompileGate;
use Gates\Application\NativeProbe;
use Gates\Application\NativeToolchain;
use Gates\Infrastructure\GatesPackage;
use Gates\Infrastructure\ProcProcessPool;
use Gates\Infrastructure\ProjectRoot;
use Gates\Infrastructure\SystemProcessRunner;

require __DIR__ . '/project.php';

/*
 * gate:21 — the native gate. The environment is read here, at the boundary:
 * ELEPHC_BIN overrides the fork's in-place binary, AIC_NATIVE_JOBS caps how
 * many probes run at once (8 by default; each runs under nice -n 10), and the
 * child processes get the full environment plus a run-local runtime cache and
 * the shared native-package cache.
 */
$root = (new ProjectRoot())->path();
$elephc = getenv('ELEPHC_BIN');
$env = getenv();
$env['XDG_CACHE_HOME'] = $root . '/.native-gate/.cache';
$nativeCache = getenv('ELEPHC_NATIVE_CACHE');
$env['ELEPHC_NATIVE_CACHE'] = is_string($nativeCache) && $nativeCache !== ''
    ? $nativeCache
    : '/mnt/fast/cache/elephc-native';

$jobsSetting = getenv('AIC_NATIVE_JOBS');
$jobs = is_string($jobsSetting) && preg_match('/^[1-9]\\d*$/', $jobsSetting) === 1
    ? (int) $jobsSetting
    : 8;

$toolchain = new NativeToolchain(
    new SystemProcessRunner(),
    is_string($elephc) && $elephc !== '' ? $elephc : '/srv/stacks/elephc/target/release/elephc',
    $env,
);

$nativeProbe = new NativeProbe($toolchain, new ProcProcessPool($jobs, 10));

exit((new NativeCompileGate($toolchain, (new GatesPackage())->path(), $nativeProbe))->run($root));
