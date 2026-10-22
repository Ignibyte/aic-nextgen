<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain\Probe;

/**
 * One scenario's throwaway app (#119): its directory, the copy of the
 * package it requires by path, and the port its `site` names.
 */
final readonly class ProbeApp
{
    /**
     * @param string $root    The app's directory.
     * @param string $package The package copy's directory.
     * @param int    $port    The port a `ServeStep` serves it on.
     */
    public function __construct(
        public string $root,
        public string $package,
        public int $port,
    ) {
    }

    /**
     * @return string The URL it is served at.
     */
    public function site(): string
    {
        return 'http://127.0.0.1:' . $this->port;
    }
}
