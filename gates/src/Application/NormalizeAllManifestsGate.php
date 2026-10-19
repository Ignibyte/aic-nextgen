<?php

declare(strict_types=1);

namespace Gates\Application;

use Gates\Domain\ProcessRunner;

use function count;
use function fwrite;
use function mb_trim;
use function sprintf;
use function strlen;
use function substr;

use const PHP_EOL;
use const STDERR;
use const STDOUT;

/**
 * Gate #1 — composer-normalize on every manifest in the repo.
 *
 * Runs `composer normalize --dry-run` against the root `composer.json` and
 * `framework/composer.json`, so the framework manifest cannot drift out of
 * canonical order unseen.
 *
 * Exit code is the number of manifests with drift (or 1 when invocation
 * itself fails). Per-manifest output is streamed through verbatim so
 * developers see the canonical diff that composer-normalize emits.
 *
 * @api
 */
final readonly class NormalizeAllManifestsGate
{
    /**
     * @param ProcessRunner    $processRunner    Seam wrapping `proc_open`.
     * @param ProjectManifests $projectManifests The manifests the gate checks.
     */
    public function __construct(
        private ProcessRunner $processRunner,
        private ProjectManifests $projectManifests = new ProjectManifests(),
    ) {
    }

    /**
     * Run the gate against `$root` and return the CLI exit code.
     *
     * @param string   $root   Repo-root absolute path.
     * @param resource $stdout Stream success/preview lines are written to.
     * @param resource $stderr Stream drift diagnostics are written to.
     */
    public function run(string $root, mixed $stdout = STDOUT, mixed $stderr = STDERR): int
    {
        $composer = $this->resolveComposer();

        if ($composer === '') {
            fwrite($stderr, sprintf('gate:1 — composer binary not found. Install composer.%s', PHP_EOL));

            return 1;
        }

        $manifests = $this->projectManifests->paths($root);
        $failures = 0;

        foreach ($manifests as $manifest) {
            $failures += $this->normalizeOne($composer, $manifest, $root, $stdout, $stderr);
        }

        if ($failures > 0) {
            fwrite($stderr, sprintf('gate:1 — %d manifest(s) not normalized.%s', $failures, PHP_EOL));

            return 1;
        }

        fwrite($stdout, sprintf('gate:1 — %d manifest(s) normalized.%s', count($manifests), PHP_EOL));

        return 0;
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
     * Invoke `composer normalize --dry-run` on one manifest; forward its
     * output and return `1` when drift was detected, `0` otherwise.
     *
     * Returning the strict-binary `0/1` instead of the raw exit code keeps
     * the caller's accumulator a count of drifting manifests (composer-
     * normalize's higher exit codes — e.g. 2 for invocation errors — still
     * fold to `1` here, which is the gate's only interesting distinction).
     *
     * @param string   $composer Resolved composer binary path.
     * @param string   $manifest Absolute path to the manifest under test.
     * @param string   $root     Repo-root absolute path.
     * @param resource $stdout   Stream tool stdout is written to.
     * @param resource $stderr   Stream tool stderr is written to.
     */
    private function normalizeOne(
        string $composer,
        string $manifest,
        string $root,
        mixed $stdout,
        mixed $stderr,
    ): int {
        $relative = substr($manifest, strlen($root) + 1);
        fwrite($stdout, sprintf('gate:1 — analysing %s%s', $relative, PHP_EOL));

        $processResult = $this->processRunner->run([$composer, 'normalize', '--dry-run', $manifest]);
        fwrite($stdout, $processResult->stdout);
        fwrite($stderr, $processResult->stderr);

        return match ($processResult->exitCode) {
            0 => 0,
            default => 1,
        };
    }
}
