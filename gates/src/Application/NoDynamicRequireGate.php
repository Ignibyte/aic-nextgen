<?php

declare(strict_types=1);

namespace Gates\Application;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function count;
use function file_get_contents;
use function fwrite;
use function is_dir;
use function restore_error_handler;
use function set_error_handler;
use function sort;
use function sprintf;
use function str_contains;
use function str_ends_with;
use function str_replace;

use const PHP_EOL;
use const STDERR;
use const STDOUT;

/**
 * Gate #16 — no runtime-dynamic `require` / `include` in runtime source.
 *
 * The elephc AOT compiler resolves include paths at compile time and rejects
 * any path it cannot fold. Old AIC removed every runtime-dynamic include from
 * its runtime code after the fact (AIC #582) by moving the construct to the
 * boot seam and leaving readers that take an already-loaded payload. Nextgen
 * holds the rule from the first class (plan.md, decision 2); this gate is
 * what holds it.
 *
 * Scope is the runtime code: `framework/src`, `app` and `modules` (#92). `public/` and `bin/`
 * are the sanctioned homes for a dynamic include: `public/worker.php` is the
 * boot seam, and neither is compiled natively. That exclusion IS the
 * architecture, not a loophole.
 *
 * phpat cannot express this rule — it reasons about classes and their
 * dependencies, not about statements — and there is no off-the-shelf sniff for
 * it, hence a bespoke scan gate. Operand classification is delegated to
 * {@see PhpRequireStatementScanner}, which works at token level so that a
 * method literally named `require` is not mistaken for the construct.
 *
 * Pure-PHP scanner — does NOT shell out, so it needs no `ProcessRunner`.
 */
final readonly class NoDynamicRequireGate
{
    /**
     * The runtime roots, relative to the repo root: everything elephc compiles.
     */
    /**
     * Wire the gate to its operand classifier.
     *
     * @param PhpRequireStatementScanner $phpRequireStatementScanner Token-level include classifier.
     * @param ProjectProfile             $projectProfile             The runtime roots, by the project's profile (#130).
     */
    public function __construct(
        private PhpRequireStatementScanner $phpRequireStatementScanner = new PhpRequireStatementScanner(),
        private ProjectProfile $projectProfile = new ProjectProfile(),
    ) {
    }

    /**
     * Run the gate against `$root` and return the CLI exit code.
     *
     * @param string   $root   Repo-root absolute path the gate scans.
     * @param resource $stdout Stream success messages are written to.
     * @param resource $stderr Stream failure messages are written to.
     */
    public function run(string $root, mixed $stdout = STDOUT, mixed $stderr = STDERR): int
    {
        $files = $this->locateRuntimeSourceFiles($root);
        $scan = $this->scanFiles($files, $root);

        if ($scan['violations'] !== [] || $scan['unreadable'] !== []) {
            $this->reportViolations($scan['violations'], $scan['unreadable'], $stderr);

            return 1;
        }

        $this->reportSuccess(count($files), $stdout);

        return 0;
    }

    /**
     * Scan every file, collecting dynamic-include rows and unreadable paths.
     *
     * @param list<string> $files Absolute paths of the files to scan.
     * @param string       $root  Repo-root absolute path (used for relative display).
     *
     * @return array{violations: list<string>, unreadable: list<string>}
     */
    private function scanFiles(array $files, string $root): array
    {
        $violations = [];
        $unreadable = [];

        foreach ($files as $file) {
            $contents = self::readFile($file);

            if ($contents === false) {
                $unreadable[] = str_replace($root . '/', '', $file);

                continue;
            }

            $violations = [
                ...$violations,
                ...$this->inspectSource($contents, $file, $root),
            ];
        }

        return ['violations' => $violations, 'unreadable' => $unreadable];
    }

    /**
     * Write the all-clean summary line to `$stdout`.
     *
     * @param int      $scanned Number of files scanned.
     * @param resource $stdout  Stream to write to.
     */
    private function reportSuccess(int $scanned, mixed $stdout): void
    {
        fwrite(
            $stdout,
            sprintf(
                'gate:16 — no runtime-dynamic require/include in runtime source: %d files scanned.%s',
                $scanned,
                PHP_EOL,
            ),
        );
    }

    /**
     * Scan one PHP source string and render any dynamic include sites as
     * `<relative-path>:<line> — <construct>` messages.
     *
     * @param string $contents Raw PHP source text.
     * @param string $file     Absolute path to the PHP source file.
     * @param string $root     Repo-root absolute path (used for relative display).
     *
     * @return list<string>
     */
    private function inspectSource(string $contents, string $file, string $root): array
    {
        $messages = [];

        foreach ($this->phpRequireStatementScanner->scan($contents) as $site) {
            $messages[] = sprintf(
                '%s:%d — %s',
                str_replace($root . '/', '', $file),
                $site['line'],
                $site['construct'],
            );
        }

        return $messages;
    }

    /**
     * Enumerate every `*.php` file under the runtime roots.
     *
     * @param string $root Repo-root absolute path.
     *
     * @return list<string>
     */
    private function locateRuntimeSourceFiles(string $root): array
    {
        $files = [];

        foreach ($this->projectProfile->roots($root)->runtimeFolders() as $sourceDir) {
            $files = [
                ...$files,
                ...$this->collectPhpFiles($root . '/' . $sourceDir),
            ];
        }

        sort($files);

        return $files;
    }

    /**
     * Recursively collect `*.php` files under one runtime root.
     *
     * @param string $sourceDir Absolute path to the runtime root.
     *
     * @return list<string>
     */
    private function collectPhpFiles(string $sourceDir): array
    {
        if (!is_dir($sourceDir)) {
            return [];
        }

        $files = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourceDir, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $entry) {
            if (!($entry instanceof SplFileInfo) || !self::isPhpEntry($entry)) {
                continue;
            }

            $files[] = $entry->getPathname();
        }

        return $files;
    }

    /**
     * Write the failure report to `$stderr`: the `path:line — construct`
     * violation rows (with resolution guidance), and/or the unreadable-file
     * diagnostic rows (their own class: a read failure is fail-closed but
     * gets no misdirected remediation).
     *
     * @param list<string> $violations Dynamic-include rows collected during the scan.
     * @param list<string> $unreadable Relative paths that could not be read.
     * @param resource     $stderr     Stream to write to.
     */
    private function reportViolations(array $violations, array $unreadable, mixed $stderr): void
    {
        if ($violations !== []) {
            $this->reportDynamicSites($violations, $stderr);
        }

        foreach ($unreadable as $path) {
            fwrite($stderr, sprintf('gate:16 — cannot read %s (fail-closed; fix permissions and re-run).%s', $path, PHP_EOL));
        }
    }

    /**
     * Write the dynamic-site rows + resolution guidance to `$stderr`.
     *
     * @param list<string> $violations Dynamic-include rows collected during the scan.
     * @param resource     $stderr     Stream to write to.
     */
    private function reportDynamicSites(array $violations, mixed $stderr): void
    {
        fwrite(
            $stderr,
            sprintf(
                'gate:16 — %d runtime-dynamic require/include site(s) in runtime source (elephc cannot fold these):%s%s',
                count($violations),
                PHP_EOL,
                PHP_EOL,
            ),
        );

        foreach ($violations as $violation) {
            fwrite($stderr, sprintf('  - %s%s', $violation, PHP_EOL));
        }

        fwrite($stderr, PHP_EOL . 'Resolution: keep the reader pure — take the already-loaded payload as a' . PHP_EOL);
        fwrite($stderr, 'parameter and move the require to the boot seam in public/worker.php or bin/,' . PHP_EOL);
        fwrite($stderr, "spelled as a literal: require __DIR__ . '/../build/x.php'." . PHP_EOL);
        fwrite($stderr, 'A path may still be computed at runtime — it just may not be the require operand.' . PHP_EOL);
    }

    /**
     * Whether one (already-narrowed) directory entry is a `*.php` regular file outside a module's templates.
     *
     * @param SplFileInfo $entry Entry yielded by the recursive iterator.
     */
    private static function isPhpEntry(SplFileInfo $entry): bool
    {
        // Templates are checked by gate:25, not the class tools (Chad, 2026-10-21, #96).
        return $entry->isFile() && str_ends_with($entry->getFilename(), '.php') && !str_contains($entry->getPathname(), '/templates/');
    }

    /**
     * Read one file with PHP warnings muted; `false` marks it unreadable
     * (reported as its own diagnostic class, never as a
     * `path:line — construct` violation row).
     *
     * @param string $file Absolute path to the PHP source file.
     */
    private static function readFile(string $file): false|string
    {
        set_error_handler(static fn (): bool => true);

        try {
            return file_get_contents($file);
        } finally {
            restore_error_handler();
        }
    }
}
