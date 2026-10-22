<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use AIC\Workflow\Application\GateExecutor;
use AIC\Workflow\Application\ProcessRunner;
use AIC\Workflow\Domain\ProcessResult;
use Override;

use function array_any;
use function explode;
use function preg_match;
use function readlink;
use function realpath;
use function restore_error_handler;
use function set_error_handler;
use function trim;

/**
 * The project's gate suite (#101): `composer run gate`, which aic/gates'
 * runner answers (#116), with the composer timeout off and the run's own
 * timeout from `config/workflow.php`; the gates' names come from
 * `vendor/bin/aic-gate --numbers`. `pgrep` finds a run already live in the
 * project, since two gate runs there corrupt each other's caches.
 */
final readonly class ComposerGateExecutor implements GateExecutor
{
    /**
     * @param string        $root          The project's root.
     * @param ProcessRunner $processRunner Runs the commands.
     * @param int           $timeout       Seconds the whole suite may take.
     */
    public function __construct(
        private string $root,
        private ProcessRunner $processRunner,
        private int $timeout,
    ) {
    }

    /**
     * @return list<string> The suite's gates, `gate:N` by N, as `vendor/bin/aic-gate --numbers` names them (#116); none
     *                      when aic/gates is not installed or does not answer.
     */
    #[Override]
    public function names(): array
    {
        $processResult = $this->processRunner->run([$this->root . '/vendor/bin/aic-gate', '--numbers'], 60);
        $names = [];

        foreach ($processResult->exitCode === 0 ? explode("\n", $processResult->output) : [] as $line) {
            if (preg_match('/^gate:\\d+$/', trim($line)) !== 1) {
                continue;
            }

            $names[] = trim($line);
        }

        return $names;
    }

    /**
     * @return bool Whether `pgrep` finds aic/gates' `gate-runner.sh` running in this project, from wherever the package
     *              is installed (#116); another project's (Lotline runs its own on the same machine) shares no cache with it.
     */
    #[Override]
    public function busy(): bool
    {
        $pids = $this->processRunner->run(['pgrep', '-f', '^\\S*sh \\S*/gate-runner\\.sh$'], 10)->output;
        $root = realpath($this->root);

        return $root !== false && array_any(
            explode("\n", trim($pids)),
            fn (string $pid): bool => $pid !== '' && $this->workingDirectory($pid) === $root,
        );
    }

    /**
     * @return ProcessResult `composer run --timeout=0 gate`, with `COMPOSER_PROCESS_TIMEOUT=0` set through env(1).
     */
    #[Override]
    public function run(): ProcessResult
    {
        return $this->processRunner->run(['env', 'COMPOSER_PROCESS_TIMEOUT=0', 'composer', 'run', '--timeout=0', 'gate'], $this->timeout);
    }

    /**
     * @param string $pid A process id.
     *
     * @return string|false Its working directory; false when it is gone or not ours to read. PHP's warning is dropped.
     */
    private function workingDirectory(string $pid): string|false
    {
        set_error_handler(static fn (): bool => true);

        try {
            return readlink('/proc/' . $pid . '/cwd');
        } finally {
            restore_error_handler();
        }
    }
}
