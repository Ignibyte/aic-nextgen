<?php

declare(strict_types=1);

namespace Gates\Domain;

/**
 * Where gate:23 runs the two sites, read at its entry script: the environment
 * both get, whether Postgres answers, the two local ports, and the app's
 * request body cap (#196), which each server is started to let through.
 */
final readonly class NativeSiteSetup
{
    /**
     * What each server takes above the app's cap, so a body just over it reaches the app's own 413.
     */
    private const int HEADROOM = 1_048_576;

    /**
     * @param array<string, string> $env          The environment both sites run with (the `PG*` settings among it).
     * @param string                $database     The Postgres address the sites use, `host:port`.
     * @param bool                  $reachable    Whether Postgres answered at that address.
     * @param int                   $phpPort      The interpreted site's port.
     * @param int                   $nativePort   The compiled site's port.
     * @param int                   $maxBodyBytes The app's request body cap, `AppConfig::$maxBodyBytes`.
     */
    public function __construct(
        public array $env,
        public string $database,
        public bool $reachable,
        public int $phpPort,
        public int $nativePort,
        public int $maxBodyBytes,
    ) {
    }

    /**
     * @return list<string> The compiled server's arguments for the cap.
     */
    public function nativeLimit(): array
    {
        return ['--max-body-size', (string) ($this->maxBodyBytes + self::HEADROOM)];
    }

    /**
     * @return list<string> php -S's arguments for the cap: the whole body, and one file up to the cap.
     */
    public function phpLimit(): array
    {
        return [
            '-d',
            'post_max_size=' . ($this->maxBodyBytes + self::HEADROOM),
            '-d',
            'upload_max_filesize=' . $this->maxBodyBytes,
        ];
    }
}
