<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use function file_get_contents;
use function file_put_contents;
use function is_resource;
use function posix_setsid;
use function proc_close;
use function proc_open;

use const STDERR;
use const STDIN;
use const STDOUT;

/** Starts a command only after its isolated session has been acknowledged by its owner. */
final readonly class SessionCommand
{
    /**
     * @param list<string> $argv Original command and arguments.
     *
     * @return int Zero after reporting the command result, or 127 if isolation failed.
     */
    public function run(array $argv): int
    {
        if (posix_setsid() === -1) {
            return 127;
        }

        // Each operation closes its php://fd duplicate before the command can inherit it.
        if (file_put_contents('php://fd/3', "R\n") !== 2 || file_get_contents('php://fd/4', length: 1) !== 'A') {
            return 127;
        }

        file_put_contents('php://fd/3', 'E' . $this->command($argv) . "\n");

        return 0;
    }

    /**
     * @param list<string> $argv The command to start without inheriting private control descriptors.
     *
     * @return int Its actual proc_close exit, or 127 when no command could start.
     */
    private function command(array $argv): int
    {
        if ($argv === []) {
            return 127;
        }

        $pipes = [];
        $descriptors = [0 => STDIN, 1 => STDOUT, 2 => STDERR, 3 => ['file', '/dev/null', 'w'], 4 => ['file', '/dev/null', 'r']];
        $command = proc_open($argv, $descriptors, $pipes);

        return is_resource($command)
            ? proc_close($command)
            : 127;
    }
}
