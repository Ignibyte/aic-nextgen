<?php

declare(strict_types=1);

namespace Gates\Application;

use Gates\Domain\ProcessRunner;

use function count;
use function fwrite;
use function implode;
use function is_executable;
use function sprintf;

use const PHP_EOL;
use const STDERR;
use const STDOUT;

/**
 * Gate #14 — copy-paste detection (systemsdk/phpcpd ^9, root require-dev).
 *
 * Min 4 lines, 50 tokens, fuzzy, threshold 0. Skips cleanly when no PHP
 * source exists yet. Ported from old AIC's gate:9 (#2).
 */
final readonly class DeduplicatorGate
{
    /**
     * @param ProcessRunner $processRunner Seam wrapping `proc_open`.
     * @param PhpFileFinder $phpFileFinder Recursive `*.php` directory predicate.
     */
    public function __construct(
        private ProcessRunner $processRunner,
        private PhpFileFinder $phpFileFinder = new PhpFileFinder(),
    ) {
    }

    /**
     * Run phpcpd against every existing PHP-source path and return the CLI exit code.
     *
     * @param string   $root   Repo-root absolute path.
     * @param resource $stdout Stream success/preview lines are written to.
     * @param resource $stderr Stream failure messages are written to.
     */
    public function run(string $root, mixed $stdout = STDOUT, mixed $stderr = STDERR): int
    {
        $existing = $this->collectExistingPhpDirs($root);

        if (count($existing) < 1) {
            fwrite($stdout, sprintf('gate:14 (phpcpd) — no PHP source files yet, nothing to scan.%s', PHP_EOL));

            return 0;
        }

        $bin = $root . '/vendor/bin/phpcpd';

        if (!is_executable($bin)) {
            fwrite($stderr, $this->renderMissingBinary());

            return 1;
        }

        $command = $this->buildCommand($bin, $existing);
        $processResult = $this->processRunner->run($command);
        fwrite($stdout, sprintf('gate:14 — %s%s%s', implode(' ', $command), PHP_EOL, $processResult->stdout));
        fwrite($stderr, $processResult->stderr);

        return $processResult->exitCode;
    }

    /**
     * Collect the set of source directories that currently contain `.php` files.
     *
     * @param string $root Repo-root absolute path.
     *
     * @return list<string>
     */
    private function collectExistingPhpDirs(string $root): array
    {
        $candidates = $this->candidateSourceDirs($root);
        $existing = [];

        foreach ($candidates as $candidate) {
            if (!$this->phpFileFinder->containsPhpFiles($candidate)) {
                continue;
            }

            $existing[] = $candidate;
        }

        return $existing;
    }

    /**
     * Build the candidate list: the framework, the app and the gate tooling.
     *
     * @param string $root Repo-root absolute path.
     *
     * @return list<string>
     */
    private function candidateSourceDirs(string $root): array
    {
        return [
            $root . '/framework/src',
            $root . '/app',
            $root . '/gates/src',
            $root . '/gates/architecture',
        ];
    }

    /**
     * Build the array-form command for `proc_open`.
     *
     * @param string       $bin      Resolved phpcpd binary path.
     * @param list<string> $existing Source directories with PHP files.
     *
     * @return list<string>
     */
    private function buildCommand(string $bin, array $existing): array
    {
        return [
            $bin,
            '--min-lines=4',
            '--min-tokens=50',
            '--fuzzy',
            ...$existing,
        ];
    }

    /**
     * Render the missing-binary error message as one deterministic string.
     */
    private function renderMissingBinary(): string
    {
        $template = 'gate:14 — vendor/bin/phpcpd not found.%s'
            . 'Run: composer install%s';

        return sprintf($template, PHP_EOL, PHP_EOL);
    }
}
