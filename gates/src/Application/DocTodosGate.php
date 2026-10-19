<?php

declare(strict_types=1);

namespace Gates\Application;

use FilesystemIterator;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function count;
use function file_get_contents;
use function fwrite;
use function in_array;
use function mb_strtolower;
use function restore_error_handler;
use function set_error_handler;
use function sort;
use function sprintf;
use function str_replace;

use const PHP_EOL;
use const STDERR;
use const STDOUT;

/**
 * Gate #19 — documentation freshness.
 *
 * Documentation is source too: a README that points at a missing file is
 * the doc-equivalent of a baseline — a debt marker that shipped instead
 * of being paid down. This gate fails on two patterns:
 *
 *   1. Literal `(TODO)` / `(WIP)` parenthesized markers in any markdown
 *      file. These are doc-stale signals that accumulate silently
 *      because no other gate cares about them.
 *   2. Relative markdown links `[text](path.md)` whose target is absent
 *      on disk. A dead link is an unkept promise.
 *
 * Inline code spans (backtick-quoted text on a line) are stripped before
 * the regex passes; fenced code blocks are skipped entirely. External
 * links (`http://`, `https://`, `mailto:`, …), pure anchors (`#section`),
 * and non-markdown link targets are ignored.
 *
 * Per-file scanning logic lives in MarkdownLineScanner.
 *
 * @api
 */
final readonly class DocTodosGate
{
    private const array SKIP_DIRS = [
        'vendor',
        'node_modules',
        '.git',
        '.claude',
        '.ddev',
        '.playwright-mcp',
        '.native-gate',
        '.psalm-cache',
        '.rector-cache',
        'tools',
        'storage',
    ];

    /**
     * Paths skipped by location, relative to the repo root. `docs/planning` is
     * the private planning repo: a checkout of its own, absent from most clones.
     */
    private const array SKIP_PATHS = [
        'docs/planning',
    ];

    /**
     * @param MarkdownLineScanner $markdownLineScanner Per-file (TODO) + dead-link scanner.
     */
    public function __construct(
        private MarkdownLineScanner $markdownLineScanner = new MarkdownLineScanner(),
    ) {
    }

    /**
     * Run the gate against `$root` and return the CLI exit code.
     *
     * @param string   $root   Repo-root absolute path.
     * @param resource $stdout Stream success messages are written to.
     * @param resource $stderr Stream failure messages are written to.
     */
    public function run(string $root, mixed $stdout = STDOUT, mixed $stderr = STDERR): int
    {
        $files = $this->collectMarkdownFiles($root);
        $report = $this->scanFiles($root, $files);

        if ($report['todos'] !== [] || $report['deadLinks'] !== []) {
            $this->reportFailures($report['todos'], $report['deadLinks'], $stderr);

            return 1;
        }

        fwrite($stdout, sprintf(
            'gate:19 — documentation freshness clean: %d markdown file(s) scanned, 0 dead link(s), 0 (TODO) marker(s).%s',
            count($files),
            PHP_EOL,
        ));

        return 0;
    }

    /**
     * Collect every markdown file the gate should inspect.
     *
     * @param string $root Repo-root absolute path.
     *
     * @return list<string>
     */
    private function collectMarkdownFiles(string $root): array
    {
        $hits = [];

        foreach ($this->iterateTree($root) as $entry) {
            if (!$this->isMarkdownFile($entry)) {
                continue;
            }

            $hits[] = $entry->getPathname();
        }

        sort($hits);

        return $hits;
    }

    /**
     * Recursively iterate over every node under `$root`, skipping vendor/cache directories.
     *
     * @param string $root Repo-root absolute path.
     *
     * @return iterable<SplFileInfo>
     */
    private function iterateTree(string $root): iterable
    {
        return new RecursiveIteratorIterator(
            new RecursiveCallbackFilterIterator(
                new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
                static fn (SplFileInfo $current): bool => !$current->isDir()
                    || !self::isSkippedDir($current, $root),
            ),
        );
    }

    /**
     * Decide whether `$entry` is a regular `.md` file (case-insensitive).
     *
     * @param SplFileInfo $entry Iterator entry to inspect.
     */
    private function isMarkdownFile(SplFileInfo $entry): bool
    {
        return $entry->isFile() && mb_strtolower($entry->getExtension()) === 'md';
    }

    /**
     * Scan every file once, returning the running todo + dead-link buckets.
     *
     * @param string       $root  Repo-root absolute path.
     * @param list<string> $files Absolute paths of markdown files to scan.
     *
     * @return array{todos: list<string>, deadLinks: list<string>}
     */
    private function scanFiles(string $root, array $files): array
    {
        $report = ['todos' => [], 'deadLinks' => []];

        foreach ($files as $file) {
            $report = $this->scanFile($root, $file, $report);
        }

        return $report;
    }

    /**
     * Fold one file's findings into the running report.
     *
     * @param string                                              $root   Repo-root absolute path.
     * @param string                                              $file   Absolute path to the markdown file.
     * @param array{todos: list<string>, deadLinks: list<string>} $report Running report tally.
     *
     * @return array{todos: list<string>, deadLinks: list<string>}
     */
    private function scanFile(string $root, string $file, array $report): array
    {
        set_error_handler(static fn (): bool => true);

        try {
            $contents = file_get_contents($file);
        } finally {
            restore_error_handler();
        }

        if ($contents === false) {
            $report['deadLinks'][] = sprintf(
                '%s — cannot read',
                $this->markdownLineScanner->relativePath($root, $file),
            );

            return $report;
        }

        return $this->markdownLineScanner->scan($root, $file, $contents, $report);
    }

    /**
     * Emit the failure report (TODO markers + dead links) to `$stderr`.
     *
     * @param list<string> $todos     Found `(TODO)` / `(WIP)` markers.
     * @param list<string> $deadLinks Found dead-link references.
     * @param resource     $stderr    Stream to write to.
     */
    private function reportFailures(array $todos, array $deadLinks, mixed $stderr): void
    {
        fwrite($stderr, 'gate:19 — documentation freshness violation.' . PHP_EOL . PHP_EOL);

        if ($todos !== []) {
            $this->emitBucket('(TODO)/(WIP) markers', $todos, $stderr);
        }

        if ($deadLinks !== []) {
            $this->emitBucket('Dead markdown links', $deadLinks, $stderr);
        }

        fwrite(
            $stderr,
            'Resolution: write the missing doc, drop the stale (TODO) marker, or fix the broken link.' . PHP_EOL,
        );
    }

    /**
     * Emit one labelled bucket of failure messages.
     *
     * @param string       $label    Bucket label (e.g. `Dead markdown links`).
     * @param list<string> $messages Per-finding messages.
     * @param resource     $stderr   Stream to write to.
     */
    private function emitBucket(string $label, array $messages, mixed $stderr): void
    {
        fwrite($stderr, sprintf('%s (%d):%s', $label, count($messages), PHP_EOL));

        foreach ($messages as $message) {
            fwrite($stderr, sprintf('  - %s%s', $message, PHP_EOL));
        }

        fwrite($stderr, PHP_EOL);
    }

    /**
     * Decide whether a directory is skipped, by name or by its path under `$root`.
     *
     * @param SplFileInfo $dir  Directory entry to inspect.
     * @param string      $root Repo-root absolute path.
     */
    private static function isSkippedDir(SplFileInfo $dir, string $root): bool
    {
        return in_array($dir->getFilename(), self::SKIP_DIRS, true)
            || in_array(str_replace($root . '/', '', $dir->getPathname()), self::SKIP_PATHS, true);
    }
}
