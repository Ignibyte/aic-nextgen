<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Workflow\Domain\ProcessResult;

/**
 * Runs a process in the project for the engine (#101): the gate run, and
 * the commands used by its probes and guards.
 */
interface ProcessRunner
{
    /**
     * @param list<string> $argv    The program and its arguments, never through a shell; the engine's environment.
     * @param int          $timeout Seconds covering launch, input and execution; owned group teardown follows on expiry.
     * @param string       $input   What it reads on stdin (#104); empty for nothing.
     *
     * @return ProcessResult Actual exit and merged output; 124 on timeout, 127 on start failure.
     */
    public function run(array $argv, int $timeout, string $input = ''): ProcessResult;
}
