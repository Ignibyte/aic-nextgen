<?php

declare(strict_types=1);

namespace Gates\Application;

use function array_pad;
use function dirname;
use function explode;
use function is_file;
use function mb_ltrim;
use function mb_strtoupper;
use function mb_substr;
use function mb_trim;
use function preg_match;
use function preg_match_all;
use function preg_replace;
use function realpath;
use function sprintf;
use function str_replace;
use function str_starts_with;

use const DIRECTORY_SEPARATOR;

/**
 * Per-file scanner for {@see DocTodosGate}: walks each markdown file's
 * lines (skipping triple-backtick fenced blocks), records `(TODO)` /
 * `(WIP)` markers, and flags relative `.md` link targets that resolve
 * to a missing file on disk.
 *
 * Split from `DocTodosGate` to keep the gate class under PHPMD's
 * ExcessiveClassLength threshold. Stateless and side-effect-free.
 *
 * @api
 */
final readonly class MarkdownLineScanner
{
    /**
     * Scan every line of `$contents`, folding findings into `$report`.
     *
     * @param string                                              $root     Repo-root absolute path.
     * @param string                                              $file     Absolute path to the source file.
     * @param string                                              $contents Raw file contents.
     * @param array{todos: list<string>, deadLinks: list<string>} $report   Running report tally.
     *
     * @return array{todos: list<string>, deadLinks: list<string>}
     */
    public function scan(string $root, string $file, string $contents, array $report): array
    {
        $relative = $this->relativePath($root, $file);
        $inFence = false;
        $lineNumber = 0;

        foreach (explode("\n", $contents) as $line) {
            $lineNumber += 1;
            $isFence = $this->isFenceBoundary($line);

            if ($isFence) {
                $inFence = !$inFence;

                continue;
            }

            if ($inFence) {
                continue;
            }

            $report = $this->scanLine($root, $file, $relative, $lineNumber, $line, $report);
        }

        return $report;
    }

    /**
     * Compute `$path` relative to `$root` (no trailing-slash normalisation).
     *
     * @param string $root Repo-root absolute path.
     * @param string $path Absolute path to a project file.
     */
    public function relativePath(string $root, string $path): string
    {
        return str_replace($root . DIRECTORY_SEPARATOR, '', $path);
    }

    /**
     * Decide whether `$line` is a triple-backtick fence boundary.
     *
     * @param string $line One markdown line.
     */
    private function isFenceBoundary(string $line): bool
    {
        return str_starts_with(mb_ltrim($line), '```');
    }

    /**
     * Apply the (TODO)/(WIP) and dead-link checks to one line.
     *
     * @param string                                              $root       Repo-root absolute path.
     * @param string                                              $file       Absolute path to the source file.
     * @param string                                              $relative   Pre-computed relative path.
     * @param int                                                 $lineNumber 1-based line number.
     * @param string                                              $line       Raw line contents.
     * @param array{todos: list<string>, deadLinks: list<string>} $report     Running report tally.
     *
     * @return array{todos: list<string>, deadLinks: list<string>}
     */
    private function scanLine(
        string $root,
        string $file,
        string $relative,
        int $lineNumber,
        string $line,
        array $report,
    ): array {
        $stripped = $this->stripInlineCodeSpans($line);
        $todo = $this->detectTodoMarker($relative, $lineNumber, $line, $stripped);

        if ($todo !== null) {
            $report['todos'][] = $todo;
        }

        $report['deadLinks'] = [
            ...$report['deadLinks'],
            ...$this->detectDeadLinks($root, $file, $relative, $stripped),
        ];

        return $report;
    }

    /**
     * Return the formatted (TODO)/(WIP) hit, or `null` when none is present.
     *
     * @param string $relative   Relative path to the source file.
     * @param int    $lineNumber 1-based line number.
     * @param string $line       Raw line (used for the human-readable suffix).
     * @param string $stripped   Same line with inline code spans removed.
     */
    private function detectTodoMarker(string $relative, int $lineNumber, string $line, string $stripped): ?string
    {
        if (preg_match('/\\((TODO|WIP)\\)/i', $stripped, $matches) !== 1) {
            return null;
        }

        return sprintf(
            '%s:%d — (%s) marker: %s',
            $relative,
            $lineNumber,
            mb_strtoupper($matches[1]),
            mb_trim($line),
        );
    }

    /**
     * Return every dead-link finding for the line.
     *
     * @param string $root     Repo-root absolute path.
     * @param string $file     Absolute path to the source file.
     * @param string $relative Pre-computed relative path.
     * @param string $stripped Line with inline code spans removed.
     *
     * @return list<string>
     */
    private function detectDeadLinks(string $root, string $file, string $relative, string $stripped): array
    {
        $linkCount = preg_match_all('/\\[[^\\]]*\\]\\(([^)]+)\\)/', $stripped, $linkMatches);

        if ($linkCount === false || $linkCount === 0) {
            return [];
        }

        $deadLinks = [];

        foreach ($linkMatches[1] as $linkTarget) {
            $deadLink = $this->checkLinkTarget($root, $file, $relative, $linkTarget);

            if ($deadLink === null) {
                continue;
            }

            $deadLinks[] = $deadLink;
        }

        return $deadLinks;
    }

    /**
     * Decide whether one regex-extracted link target points at a missing markdown file.
     *
     * @param string $root       Repo-root absolute path.
     * @param string $file       Absolute path to the source file.
     * @param string $relative   Pre-computed relative path of the source file.
     * @param string $linkTarget Raw link target between the parentheses.
     */
    private function checkLinkTarget(string $root, string $file, string $relative, string $linkTarget): ?string
    {
        $resolved = $this->resolveLinkTarget($file, $linkTarget);

        if ($resolved === null || is_file($resolved)) {
            return null;
        }

        return sprintf(
            '%s — dead link: %s -> %s',
            $relative,
            $linkTarget,
            $this->relativePath($root, $resolved),
        );
    }

    /**
     * Resolve a markdown link target relative to its source file.
     *
     * Returns `null` when the link is external, a pure anchor, or points at
     * a non-markdown file (this gate scopes its dead-link check to `.md`).
     *
     * @param string $sourceFile Absolute path to the markdown file the link appears in.
     * @param string $rawTarget  Raw link target between the parentheses.
     */
    private function resolveLinkTarget(string $sourceFile, string $rawTarget): ?string
    {
        $target = mb_trim($rawTarget);

        if ($target === '' || $this->isExternalTarget($target)) {
            return null;
        }

        [$path] = array_pad(explode('#', $target, 2), 2, '');

        if ($path === '' || mb_substr($path, -3) !== '.md') {
            return null;
        }

        $base = dirname($sourceFile);
        $resolved = realpath($base . DIRECTORY_SEPARATOR . $path);

        if ($resolved === false) {
            return $base . DIRECTORY_SEPARATOR . $path;
        }

        return $resolved;
    }

    /**
     * Decide whether `$target` is an external scheme or pure anchor.
     *
     * @param string $target Trimmed link target.
     */
    private function isExternalTarget(string $target): bool
    {
        return preg_match('/^(https?:|mailto:|ftp:|tel:|#)/i', $target) === 1;
    }

    /**
     * Strip inline backtick-quoted code spans from `$line` so the marker /
     * link regexes do not treat documentation-of-syntax as real markdown.
     *
     * @param string $line One markdown line.
     */
    private function stripInlineCodeSpans(string $line): string
    {
        return (string) preg_replace('/`[^`]*`/', '', $line);
    }
}
