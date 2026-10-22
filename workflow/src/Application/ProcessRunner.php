<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Workflow\Domain\ProcessResult;

/**
 * Runs a process in the project for the engine (#101): the gate run, and
 * `pgrep` for a live one.
 */
interface ProcessRunner
{
    /**
     * @param list<string> $argv    The program and its arguments, never through a shell; the engine's environment.
     * @param int          $timeout Seconds before it is stopped and reads as exit 124.
     * @param string       $input   What it reads on stdin (#104); empty for nothing.
     *
     * @return ProcessResult Its exit code and output; exit 127 when it could not start.
     */
    public function run(array $argv, int $timeout, string $input = ''): ProcessResult;
}
