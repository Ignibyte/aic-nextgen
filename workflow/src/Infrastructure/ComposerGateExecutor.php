<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use AIC\Workflow\Application\GateExecutor;
use AIC\Workflow\Application\ProcessRunner;
use AIC\Workflow\Domain\ProcessResult;
use Override;
use stdClass;

use function array_any;
use function array_keys;
use function explode;
use function file_get_contents;
use function get_object_vars;
use function is_file;
use function json_decode;
use function preg_match;
use function readlink;
use function realpath;
use function restore_error_handler;
use function set_error_handler;
use function substr;
use function trim;
use function usort;

/**
 * The project's gate suite (#101): `composer run gate`, which AIC's
 * `bin/gate-runner.sh` answers, with the composer timeout off and the run's
 * own timeout from `config/workflow.php`. `pgrep` finds a run already live
 * in the project, since two gate runs there corrupt each other's caches.
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
     * @return list<string> The `gate:N` scripts in the project's composer.json, by N.
     */
    #[Override]
    public function names(): array
    {
        $names = [];

        foreach ($this->scripts() as $name) {
            if (preg_match('/^gate:\\d+$/', $name) !== 1) {
                continue;
            }

            $names[] = $name;
        }

        usort($names, static fn (string $left, string $right): int => (int) substr($left, 5) <=> (int) substr($right, 5));

        return $names;
    }

    /**
     * @return bool Whether `pgrep` finds a `bin/gate-runner.sh` running in this project; another project's (Lotline runs
     *              its own on the same machine) shares no cache with it.
     */
    #[Override]
    public function busy(): bool
    {
        $pids = $this->processRunner->run(['pgrep', '-f', '^\\S*sh \\S*bin/gate-runner\\.sh$'], 10)->output;
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

    /**
     * @return list<string> The script names in the project's composer.json.
     */
    private function scripts(): array
    {
        $path = $this->root . '/composer.json';
        $composer = is_file($path)
            ? json_decode((string) file_get_contents($path))
            : null;
        $scripts = $composer instanceof stdClass
            ? $composer->scripts ?? null
            : null;
        $names = [];

        foreach ($scripts instanceof stdClass ? array_keys(get_object_vars($scripts)) : [] as $name) {
            $names[] = (string) $name;
        }

        return $names;
    }
}
