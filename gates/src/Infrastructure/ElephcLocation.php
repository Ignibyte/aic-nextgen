<?php

declare(strict_types=1);

namespace Gates\Infrastructure;

use function explode;
use function is_executable;
use function realpath;

use const PATH_SEPARATOR;

/**
 * Where the native gates find elephc and its native-package cache (#222):
 * `ELEPHC_BIN`; else `tools/elephc`, the pinned release `aic-gate tools`
 * links there (#281), so an upstream elephc on `PATH` never wins over it;
 * else the first `elephc` on `PATH`. `ELEPHC_NATIVE_CACHE`, else
 * `~/.cache/elephc-native`. A compiler found by a link is resolved through it,
 * so the bridge archives beside the real binary stay part of gate:21's cache
 * identity.
 */
final readonly class ElephcLocation
{
    /**
     * @param array<string, string> $env  The process environment.
     * @param string                $root The project's root.
     *
     * @return string The compiler to run; the bare name `elephc` when none is found.
     */
    public function compiler(array $env, string $root): string
    {
        $configured = $env['ELEPHC_BIN'] ?? '';
        $installed = $root . '/tools/elephc';

        return match (true) {
            $configured !== '' => $configured,
            is_executable($installed) => $this->resolved($installed),
            default => $this->onPath($env['PATH'] ?? ''),
        };
    }

    /**
     * @param array<string, string> $env The process environment.
     *
     * @return string The shared native-package cache directory.
     */
    public function nativeCache(array $env): string
    {
        $configured = $env['ELEPHC_NATIVE_CACHE'] ?? '';

        if ($configured !== '') {
            return $configured;
        }

        return ($env['HOME'] ?? '') . '/.cache/elephc-native';
    }

    /**
     * @param string $path The `PATH` value.
     *
     * @return string The first executable `elephc` on it, resolved through its links; `elephc` when none.
     */
    private function onPath(string $path): string
    {
        foreach (explode(PATH_SEPARATOR, $path) as $directory) {
            $candidate = $directory . '/elephc';

            if ($directory !== '' && is_executable($candidate)) {
                return $this->resolved($candidate);
            }
        }

        return 'elephc';
    }

    /**
     * @param string $candidate An executable path.
     *
     * @return string The path through its links, or as given when it does not resolve.
     */
    private function resolved(string $candidate): string
    {
        $resolved = realpath($candidate);

        return $resolved === false
            ? $candidate
            : $resolved;
    }
}
