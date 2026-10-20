<?php

declare(strict_types=1);

namespace Gates\Domain;

/**
 * Starts long-running server processes and stops every one it started.
 */
interface ServerLauncher
{
    /**
     * @param list<string>          $command The server's command line.
     * @param string                $cwd     Where it runs.
     * @param array<string, string> $env     Its environment.
     * @param int                   $port    The local port it listens on.
     * @param string                $log     Where its output goes.
     *
     * @return bool Whether it is accepting connections within the start timeout.
     */
    public function start(array $command, string $cwd, array $env, int $port, string $log): bool;

    /**
     * Stops every server this launcher started.
     */
    public function stopAll(): void;
}
