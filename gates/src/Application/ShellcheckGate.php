<?php

declare(strict_types=1);

namespace Gates\Application;

use FilesystemIterator;
use Gates\Domain\ProcessRunner;
use GlobIterator;

use function count;
use function fwrite;
use function mb_trim;
use function sprintf;
use function str_replace;

use const PHP_EOL;
use const STDERR;
use const STDOUT;

/**
 * Gate #18 — `shellcheck` lint over `.claude/hooks/` and `bin/` shell scripts.
 *
 * Severity threshold is `warning` because shellcheck `info`-level findings
 * are predominantly stylistic. Auto-skips with a clear message when the
 * tool is not on PATH AND the run is not in CI; in CI the gate hard-fails
 * on missing shellcheck.
 */
final readonly class ShellcheckGate
{
    /**
     * @param ProcessRunner $processRunner Seam wrapping `proc_open`.
     * @param bool          $isCi          Resolved CI flag (true when running under a CI runner) — passed at the wrapper boundary.
     */
    public function __construct(
        private ProcessRunner $processRunner,
        private bool $isCi = false,
    ) {
    }

    /**
     * Run shellcheck against every `.sh` under `.claude/hooks/` + `bin/` and return the CLI exit code.
     *
     * @param string   $root   Repo-root absolute path.
     * @param resource $stdout Stream success/preview lines are written to.
     * @param resource $stderr Stream failure messages are written to.
     */
    public function run(string $root, mixed $stdout = STDOUT, mixed $stderr = STDERR): int
    {
        $shellcheck = $this->resolveShellcheck();

        if ($shellcheck === '') {
            return $this->handleMissingTool($stdout, $stderr);
        }

        $scripts = $this->collectScripts($root);

        if (count($scripts) < 1) {
            fwrite($stdout, 'gate:18 — no shell scripts found under .claude/hooks/ or bin/.' . PHP_EOL);

            return 0;
        }

        $failures = $this->lintScripts($shellcheck, $scripts, $root);

        if (count($failures) > 0) {
            fwrite($stderr, $this->renderFailures($failures));

            return 1;
        }

        fwrite($stdout, sprintf('gate:18 — shellcheck: %d script(s) clean.%s', count($scripts), PHP_EOL));

        return 0;
    }

    /**
     * Locate `shellcheck` on PATH; return empty string when missing.
     */
    private function resolveShellcheck(): string
    {
        $processResult = $this->processRunner->run(['/bin/sh', '-c', 'command -v shellcheck']);

        return mb_trim($processResult->stdout);
    }

    /**
     * Collect every `.sh` under `.claude/hooks/` and `bin/`.
     *
     * `GlobIterator::CURRENT_AS_PATHNAME` already yields strings, so the
     * caller does not need a `(string)` cast here — the value is fed
     * straight into `sprintf('%s')` downstream where coercion is implicit.
     *
     * @param string $root Repo-root absolute path.
     *
     * @return list<string>
     */
    private function collectScripts(string $root): array
    {
        $scripts = [];

        foreach (['.claude/hooks', 'bin'] as $dir) {
            $iterator = new GlobIterator($root . '/' . $dir . '/*.sh', GlobIterator::CURRENT_AS_PATHNAME | FilesystemIterator::SKIP_DOTS);

            foreach ($iterator as $match) {
                /** @var string $match */
                $scripts[] = $match;
            }
        }

        return $scripts;
    }

    /**
     * Lint each script and return the list of (script, output) failure tuples.
     *
     * @param string       $shellcheck Resolved shellcheck binary path.
     * @param list<string> $scripts    Absolute paths of `.sh` scripts to lint.
     * @param string       $root       Repo-root absolute path (for relative-path display).
     *
     * @return list<array{script: string, output: string}>
     */
    private function lintScripts(string $shellcheck, array $scripts, string $root): array
    {
        $failures = [];

        foreach ($scripts as $script) {
            $result = $this->processRunner->run([$shellcheck, '-S', 'warning', $script]);

            if ($result->exitCode < 1) {
                continue;
            }

            $failures[] = [
                'output' => $result->stdout . $result->stderr,
                'script' => str_replace($root . '/', '', $script),
            ];
        }

        return $failures;
    }

    /**
     * Decide what to do when shellcheck is not on PATH.
     *
     * @param resource $stdout Stream the local-skip notice is written to.
     * @param resource $stderr Stream the CI-fail notice is written to.
     */
    private function handleMissingTool(mixed $stdout, mixed $stderr): int
    {
        if ($this->isCi) {
            fwrite($stderr, sprintf(
                'gate:18 — shellcheck not found in CI. Install via `bash bin/install-tools.sh`.%s',
                PHP_EOL,
            ));

            return 1;
        }

        fwrite($stdout, sprintf(
            'gate:18 — shellcheck not found locally; skipping. Run `bash bin/install-tools.sh` to install.%s',
            PHP_EOL,
        ));

        return 0;
    }

    /**
     * Render the full failure block (header + per-script tuple + resolution) as one string.
     *
     * @param list<array{script: string, output: string}> $failures Per-script failure tuples.
     */
    private function renderFailures(array $failures): string
    {
        $body = sprintf('gate:18 — shellcheck found issues in %d script(s).%s%s', count($failures), PHP_EOL, PHP_EOL);

        foreach ($failures as $failure) {
            $body .= sprintf(
                '--- %s ---%s%s%s%s',
                $failure['script'],
                PHP_EOL,
                $failure['output'],
                PHP_EOL,
                PHP_EOL,
            );
        }

        return $body
            . sprintf('Resolution: fix each finding at the source. No suppressions (CLAUDE.md, Quality gates).%s', PHP_EOL)
            . sprintf('shellcheck rule docs: https://www.shellcheck.net/wiki/SC<NNNN>%s', PHP_EOL);
    }
}
