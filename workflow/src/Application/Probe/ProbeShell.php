<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Probe;

use AIC\Workflow\Domain\Probe\ProbeApp;
use AIC\Workflow\Domain\ProcessResult;

/**
 * Commands run in a scenario's app (#119), its directory the working one.
 */
interface ProbeShell
{
    /**
     * @param ProbeApp              $probeApp The app.
     * @param list<string>          $argv     The command and its arguments.
     * @param array<string, string> $env      Variables it runs with, through `env(1)`.
     * @param string                $input    Its standard input.
     *
     * @return ProcessResult Its exit and output.
     */
    public function run(ProbeApp $probeApp, array $argv, array $env = [], string $input = ''): ProcessResult;
}
