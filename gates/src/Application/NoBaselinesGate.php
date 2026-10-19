<?php

declare(strict_types=1);

namespace Gates\Application;

use FilesystemIterator;
use GlobIterator;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function file_get_contents;
use function fwrite;
use function in_array;
use function is_file;
use function preg_match;
use function restore_error_handler;
use function set_error_handler;
use function sprintf;
use function str_replace;

use const PHP_EOL;
use const STDERR;
use const STDOUT;

/**
 * Gate #5 — no baselines, no suppressions.
 *
 * The gates are source-fix only (CLAUDE.md, Quality gates): "No baselines.
 * No suppressions. Source fixes only." This gate
 * fails on three classes of violation:
 *
 *   1. Baseline files at the repo root or under any package
 *      (`phpstan-baseline.neon`, `psalm-baseline.xml`, `phpcs.baseline.xml`,
 *      `.phpstan-baseline`, `rector.baseline.*`).
 *   2. Non-empty `ignoreErrors:` block in any `phpstan*.neon`.
 *   3. `errorLevel="suppress"` (or `"info"`) attribute on any `<issueHandlers>`
 *      child in `psalm.xml`.
 *
 * Pure-PHP scanner — does NOT shell out, so it does not need a `ProcessRunner`.
 *
 * @api
 */
final class NoBaselinesGate
{
    /**
     * Filenames that count as baselines anywhere in the tree.
     */
    private const array BASELINE_FILENAMES = [
        'phpstan-baseline.neon',
        '.phpstan-baseline',
        'psalm-baseline.xml',
        'phpcs.baseline.xml',
        'phpcs-baseline.xml',
        'rector.baseline.php',
        '.rector-baseline',
    ];

    /**
     * Directory names skipped during the baseline-file walk.
     */
    private const array SKIP_DIRS = [
        'vendor',
        'node_modules',
        '.git',
        'storage',
        'tools',
        '.native-gate',
        '.psalm-cache',
        '.rector-cache',
    ];

    /**
     * Run the gate against `$root` and return the CLI exit code.
     *
     * @param string   $root   Repo-root absolute path the gate scans.
     * @param resource $stdout Stream success messages are written to.
     * @param resource $stderr Stream failure messages are written to.
     */
    public function run(string $root, mixed $stdout = STDOUT, mixed $stderr = STDERR): int
    {
        $violations = $this->collectViolations($root);

        if ($violations !== []) {
            $this->reportViolations($violations, $stderr);

            return 1;
        }

        fwrite(
            $stdout,
            'gate:5 — clean: no baseline files, no ignoreErrors blocks, no psalm suppressions.' . PHP_EOL,
        );

        return 0;
    }

    /**
     * Walk `$root` and gather every baseline or suppression as a list of human-readable messages.
     *
     * @param string $root Repo-root absolute path.
     *
     * @return list<string>
     */
    private function collectViolations(string $root): array
    {
        return [
            ...$this->collectBaselineFileViolations($root),
            ...$this->collectPhpstanIgnoreErrorsViolations($root),
            ...$this->collectPsalmSuppressViolations($root),
        ];
    }

    /**
     * Look for any well-known baseline filename anywhere under `$root`.
     *
     * @param string $root Repo-root absolute path.
     *
     * @return list<string>
     */
    private function collectBaselineFileViolations(string $root): array
    {
        $violations = [];

        foreach (self::BASELINE_FILENAMES as $baselineFilename) {
            foreach ($this->locateMatchingFiles($root, $baselineFilename) as $hit) {
                $violations[] = sprintf('baseline file present: %s', str_replace($root . '/', '', $hit));
            }
        }

        return $violations;
    }

    /**
     * Inspect every `$root/phpstan*.neon` for a non-empty `ignoreErrors:` block.
     *
     * @param string $root Repo-root absolute path.
     *
     * @return list<string>
     */
    private function collectPhpstanIgnoreErrorsViolations(string $root): array
    {
        $violations = [];
        $flags = GlobIterator::CURRENT_AS_PATHNAME | FilesystemIterator::SKIP_DOTS;

        foreach (new GlobIterator($root . '/phpstan*.neon', $flags) as $phpstanConfig) {
            $violations = [
                ...$violations,
                ...$this->inspectPhpstanConfig((string) $phpstanConfig, $root),
            ];
        }

        return $violations;
    }

    /**
     * Read one `phpstan*.neon` file and return any violations it contributes.
     *
     * @param string $phpstanConfig Absolute path to the phpstan config file.
     * @param string $root          Repo-root absolute path (used for relative-path display).
     *
     * @return list<string>
     */
    private function inspectPhpstanConfig(string $phpstanConfig, string $root): array
    {
        set_error_handler(static fn (): bool => true);

        try {
            $contents = file_get_contents($phpstanConfig);
        } finally {
            restore_error_handler();
        }

        if ($contents === false) {
            return [sprintf('cannot read %s', $phpstanConfig)];
        }

        if (preg_match('/^\\s*ignoreErrors:\\s*\\n\\s+-\\s+/m', $contents) !== 1) {
            return [];
        }

        return [
            sprintf(
                'phpstan ignoreErrors block is non-empty: %s',
                str_replace($root . '/', '', $phpstanConfig),
            ),
        ];
    }

    /**
     * Inspect `$root/psalm.xml` for `errorLevel="suppress"` or `errorLevel="info"`.
     *
     * @param string $root Repo-root absolute path.
     *
     * @return list<string>
     */
    private function collectPsalmSuppressViolations(string $root): array
    {
        $psalmConfig = $root . '/psalm.xml';

        if (!is_file($psalmConfig)) {
            return [];
        }

        set_error_handler(static fn (): bool => true);

        try {
            $contents = file_get_contents($psalmConfig);
        } finally {
            restore_error_handler();
        }

        if ($contents === false) {
            return ['cannot read psalm.xml'];
        }

        if (preg_match('/errorLevel\\s*=\\s*"(suppress|info)"/i', $contents) === 1) {
            return ['psalm.xml contains errorLevel="suppress" or "info" — issue is being silenced rather than fixed'];
        }

        return [];
    }

    /**
     * Locate every file under `$root` whose basename matches `$needle`.
     *
     * Pure-PHP recursive walk — no shell tool dependency. Skips vendor,
     * .git, and other non-source directories listed in {@see self::SKIP_DIRS}.
     *
     * @param string $root   Repo-root absolute path the walk starts at.
     * @param string $needle Basename to match (case-sensitive equality).
     *
     * @return list<string>
     */
    private function locateMatchingFiles(string $root, string $needle): array
    {
        $hits = [];

        foreach ($this->buildSourceFileIterator($root) as $entry) {
            if (!$this->fileMatches($entry, $needle)) {
                continue;
            }

            $hits[] = $entry->getPathname();
        }

        return $hits;
    }

    /**
     * Decide whether an iterator entry is a file whose basename equals `$needle`.
     *
     * @param SplFileInfo $entry  Iterator entry the filter callback admitted.
     * @param string      $needle Basename to match (case-sensitive).
     */
    private function fileMatches(SplFileInfo $entry, string $needle): bool
    {
        return $entry->isFile() && $entry->getFilename() === $needle;
    }

    /**
     * Build the recursive directory iterator with {@see self::SKIP_DIRS} pruned.
     *
     * @param string $root Repo-root absolute path the walk starts at.
     *
     * @return iterable<SplFileInfo>
     */
    private function buildSourceFileIterator(string $root): iterable
    {
        return new RecursiveIteratorIterator(
            new RecursiveCallbackFilterIterator(
                new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
                static fn (SplFileInfo $current): bool => !$current->isDir() || !in_array($current->getFilename(), self::SKIP_DIRS, true),
            ),
        );
    }

    /**
     * Write the violation list + resolution guidance to `$stderr`.
     *
     * @param list<string> $violations Messages collected by {@see self::collectViolations()}.
     * @param resource     $stderr     Stream to write to.
     */
    private function reportViolations(array $violations, mixed $stderr): void
    {
        fwrite($stderr, 'gate:5 — violation: source-fix only; no baselines; no suppressions.' . PHP_EOL . PHP_EOL);

        foreach ($violations as $violation) {
            fwrite($stderr, sprintf('  - %s%s', $violation, PHP_EOL));
        }

        fwrite($stderr, PHP_EOL . 'Resolution: remove the baseline / suppression and fix the underlying source.' . PHP_EOL);
        fwrite($stderr, 'If the rule itself is genuinely wrong, change the rule for everyone; never waive one instance.' . PHP_EOL);
        fwrite($stderr, 'Suppressing a single instance is forbidden (CLAUDE.md, Quality gates).' . PHP_EOL);
    }
}
