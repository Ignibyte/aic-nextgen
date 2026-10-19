<?php

declare(strict_types=1);

namespace Gates\Application;

use Gates\Domain\ProcessRunner;

use function fwrite;
use function implode;
use function mb_trim;
use function sprintf;
use function str_contains;

use const PHP_EOL;
use const STDERR;
use const STDOUT;

/**
 * Gate #0 — `composer audit` security advisory scan.
 *
 * Runs `composer audit --abandoned=report` and exits non-zero when composer
 * reports any security advisory. Pairs with `roave/security-advisories` (which
 * blocks at install-time) and Psalm taint analysis (gate:13). Composer 2.9.x's
 * `--abandoned=fail` is unreliable for transitive abandoned deps, so abandoned
 * packages are reported but never block on their own.
 *
 * @api
 */
final readonly class ComposerAuditGate
{
    /**
     * @param ProcessRunner $processRunner Seam wrapping `proc_open`.
     */
    public function __construct(
        private ProcessRunner $processRunner,
    ) {
    }

    /**
     * Run `composer audit` and return the CLI exit code.
     *
     * @param string   $root   Repo-root absolute path (passed via `--working-dir`).
     * @param resource $stdout Stream success/preview lines are written to.
     * @param resource $stderr Stream failure messages are written to.
     */
    public function run(string $root, mixed $stdout = STDOUT, mixed $stderr = STDERR): int
    {
        $composer = $this->resolveComposer();

        if ($composer === '') {
            fwrite($stderr, sprintf('gate:0 — composer binary not found. Install composer.%s', PHP_EOL));

            return 1;
        }

        $command = $this->buildCommand($composer, $root);
        $processResult = $this->processRunner->run($command);
        $combined = $processResult->stdout . $processResult->stderr;
        fwrite($stdout, sprintf('gate:0 — %s%s%s%s', implode(' ', $command), PHP_EOL, $combined, PHP_EOL));

        return $this->classifyOutcome($processResult->exitCode, $combined, $stderr);
    }

    /**
     * Locate the `composer` binary on PATH.
     */
    private function resolveComposer(): string
    {
        $processResult = $this->processRunner->run(['/bin/sh', '-c', 'command -v composer']);

        return mb_trim($processResult->stdout);
    }

    /**
     * Build the array-form command for `proc_open`.
     *
     * @param string $composer Resolved composer binary path.
     * @param string $root     Repo-root absolute path.
     *
     * @return list<string>
     */
    private function buildCommand(string $composer, string $root): array
    {
        return [
            $composer,
            'audit',
            '--working-dir=' . $root,
            '--abandoned=report',
            '--no-cache',
            '--no-interaction',
        ];
    }

    /**
     * Decide the gate exit code from composer's exit + output.
     *
     * @param int      $exitCode Composer's exit code.
     * @param string   $combined Combined stdout + stderr from composer.
     * @param resource $stderr   Stream the failure summary is written to.
     */
    private function classifyOutcome(int $exitCode, string $combined, mixed $stderr): int
    {
        if (
            str_contains($combined, 'security vulnerability advisory')
            && !str_contains($combined, 'No security vulnerability advisories found')
        ) {
            fwrite($stderr, sprintf('gate:0 — composer audit reported security advisories (see above).%s', PHP_EOL));

            return 1;
        }

        if ($exitCode > 0) {
            fwrite($stderr, sprintf('gate:0 — composer audit exited with code %d.%s', $exitCode, PHP_EOL));

            return $exitCode;
        }

        return 0;
    }
}
