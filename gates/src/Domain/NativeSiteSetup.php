<?php

declare(strict_types=1);

namespace Gates\Domain;

/**
 * Where gate:23 runs the two sites, read at its entry script: the environment
 * both get, whether Postgres answers, and the two local ports.
 */
final readonly class NativeSiteSetup
{
    /**
     * @param array<string, string> $env        The environment both sites run with (the `PG*` settings among it).
     * @param string                $database   The Postgres address the sites use, `host:port`.
     * @param bool                  $reachable  Whether Postgres answered at that address.
     * @param int                   $phpPort    The interpreted site's port.
     * @param int                   $nativePort The compiled site's port.
     */
    public function __construct(
        public array $env,
        public string $database,
        public bool $reachable,
        public int $phpPort,
        public int $nativePort,
    ) {
    }
}
