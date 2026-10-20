<?php

declare(strict_types=1);

namespace Gates\Infrastructure;

use Gates\Domain\ServerLauncher;
use Override;

use function fclose;
use function hrtime;
use function is_resource;
use function proc_close;
use function proc_get_status;
use function proc_open;
use function proc_terminate;
use function restore_error_handler;
use function set_error_handler;
use function stream_socket_client;
use function usleep;

/**
 * Starts servers with `proc_open`, their output in a log file, and waits until
 * each accepts a connection on its port; stops them all with SIGTERM.
 */
final class ProcServerLauncher implements ServerLauncher
{
    /**
     * How long a server has to start accepting connections, in nanoseconds.
     */
    private const int START_TIMEOUT = 15_000_000_000;

    /**
     * The started processes.
     *
     * @var list<resource>
     */
    private array $processes = [];

    /**
     * @param list<string>          $command The server's command line.
     * @param string                $cwd     Where it runs.
     * @param array<string, string> $env     Its environment.
     * @param int                   $port    The local port it listens on.
     * @param string                $log     Where its output goes.
     *
     * @return bool Whether it is accepting connections within the start timeout.
     */
    #[Override]
    public function start(array $command, string $cwd, array $env, int $port, string $log): bool
    {
        $descriptors = [0 => ['file', '/dev/null', 'r'], 1 => ['file', $log, 'w'], 2 => ['file', $log, 'a']];
        $process = proc_open($command, $descriptors, $pipes, $cwd, $env);

        if (!is_resource($process) || $pipes !== []) {
            return false;
        }

        $this->processes[] = $process;

        return self::waitForPort($process, $port);
    }

    /**
     * Stops every server this launcher started.
     */
    #[Override]
    public function stopAll(): void
    {
        foreach ($this->processes as $process) {
            proc_terminate($process);
            proc_close($process);
        }

        $this->processes = [];
    }

    /**
     * @param resource $process The server.
     * @param int      $port    Its port.
     *
     * @return bool Whether it accepted a connection before the timeout, while still running.
     */
    private static function waitForPort(mixed $process, int $port): bool
    {
        $deadline = hrtime(true) + self::START_TIMEOUT;
        set_error_handler(static fn (): bool => true);

        try {
            while (hrtime(true) < $deadline && proc_get_status($process)['running']) {
                $socket = stream_socket_client('tcp://127.0.0.1:' . $port, timeout: 0.2);

                if (is_resource($socket)) {
                    fclose($socket);

                    return true;
                }

                usleep(100_000);
            }
        } finally {
            restore_error_handler();
        }

        return false;
    }
}
