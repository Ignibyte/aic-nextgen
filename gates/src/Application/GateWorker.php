<?php

declare(strict_types=1);

namespace Gates\Application;

use Gates\Domain\ProcessRunner;

use function explode;
use function mb_trim;

/**
 * The queue worker a gate:23 step with `"work": true` runs before it is sent
 * (#209): the app's own `bin/aic queue:work`, under host PHP in the app's root
 * with the sites' environment, so a page that exists only after a job runs
 * (an export's download) can be reached. It runs what is due and exits.
 */
final readonly class GateWorker
{
    /**
     * @param ProcessRunner $processRunner Runs the console.
     * @param string        $php           The PHP binary.
     */
    public function __construct(
        private ProcessRunner $processRunner,
        private string $php,
    ) {
    }

    /**
     * @param string                $root The app's root.
     * @param array<string, string> $env  The environment the sites run with.
     *
     * @return string Why the worker failed, its exit code and first line of output; empty when it ran.
     */
    public function work(string $root, array $env): string
    {
        $processResult = $this->processRunner->run([$this->php, $root . '/bin/aic', 'queue:work'], $root, $env);

        if ($processResult->exitCode === 0) {
            return '';
        }

        $first = explode("\n", mb_trim($processResult->stderr . "\n" . $processResult->stdout))[0];

        return 'bin/aic queue:work exited ' . $processResult->exitCode . ': ' . $first;
    }
}
