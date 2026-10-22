<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure\Probe;

use AIC\Workflow\Application\Probe\ProbeShell;
use AIC\Workflow\Domain\Probe\ProbeApp;
use AIC\Workflow\Domain\ProcessResult;
use AIC\Workflow\Infrastructure\LocalProcess;
use Override;

/**
 * Commands in a scenario's app (#119), its directory the working one, each
 * variable set through `env(1)` so no PHP environment call is made. One call
 * may take an hour: a phase exit runs the whole gate suite.
 */
final readonly class LocalProbeShell implements ProbeShell
{
    /**
     * The seconds one call may take.
     */
    private const int TIMEOUT = 3_600;

    /**
     * @param ProbeApp              $probeApp The app.
     * @param list<string>          $argv     The command and its arguments.
     * @param array<string, string> $env      Variables it runs with.
     * @param string                $input    Its standard input.
     *
     * @return ProcessResult Its exit and output.
     */
    #[Override]
    public function run(ProbeApp $probeApp, array $argv, array $env = [], string $input = ''): ProcessResult
    {
        $prefix = [];

        foreach ($env as $name => $value) {
            $prefix[] = $name . '=' . $value;
        }

        $command = $prefix === []
            ? $argv
            : ['env', ...$prefix, ...$argv];

        return (new LocalProcess($probeApp->root))->run($command, self::TIMEOUT, $input);
    }
}
