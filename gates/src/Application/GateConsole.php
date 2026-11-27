<?php

declare(strict_types=1);

namespace Gates\Application;

use Gates\Domain\NativeSiteStep;
use Gates\Domain\ProcessRunner;

use function explode;
use function implode;
use function mb_trim;

/**
 * The app's console as a gate:23 step runs it before it is sent: its
 * `console` words (#210), then, with `"work": true`, `queue:work` (#209), or
 * with `"work": "<worker>"` that worker's `bin/aic.php queue:work` (#336). It
 * runs under host PHP in the app's root with the sites' environment, so a
 * pass can start a record afresh or reach a page that exists only after a
 * job runs.
 */
final readonly class GateConsole
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
     * @param NativeSiteStep        $nativeSiteStep The step about to be sent.
     * @param string                $root           The app's root.
     * @param array<string, string> $env            The environment the sites run with.
     *
     * @return string Why its command or the worker failed; empty when both ran or it asks for neither.
     */
    public function before(NativeSiteStep $nativeSiteStep, string $root, array $env): string
    {
        $failed = $nativeSiteStep->console === []
            ? ''
            : $this->run($root, $env, $nativeSiteStep->console);

        if ($failed !== '' || $nativeSiteStep->work === '') {
            return $failed;
        }

        // A worker's queue is worked by its own runner, interpreted (#336).
        $console = $nativeSiteStep->work === 'default'
            ? 'bin/aic'
            : 'workers/' . $nativeSiteStep->work . '/bin/aic.php';

        return $this->run($root, $env, ['queue:work'], $console);
    }

    /**
     * @param string                $root    The app's root.
     * @param array<string, string> $env     The environment the sites run with.
     * @param list<string>          $words   The command and its arguments.
     * @param string                $console The console it runs in, relative to the root.
     *
     * @return string Why the command failed, its exit code and first line of output; empty when it ran.
     */
    private function run(string $root, array $env, array $words, string $console = 'bin/aic'): string
    {
        $processResult = $this->processRunner->run([$this->php, $root . '/' . $console, ...$words], $root, $env);

        if ($processResult->exitCode === 0) {
            return '';
        }

        $first = explode("\n", mb_trim($processResult->stderr . "\n" . $processResult->stdout))[0];

        return $console . ' ' . implode(' ', $words) . ' exited ' . $processResult->exitCode . ': ' . $first;
    }
}
