<?php

declare(strict_types=1);

namespace Gates\Application;

use function array_unique;
use function array_values;
use function dirname;
use function file_exists;
use function mb_strpos;
use function preg_match_all;
use function realpath;
use function sprintf;
use function str_ends_with;
use function str_starts_with;
use function substr;

/**
 * Resolves internal markdown links and reports dead targets.
 *
 * Walks `[text](relative-path)` patterns in a markdown body, filters out
 * external links + pure anchors + template placeholders, then probes the
 * filesystem for each remaining target. Returns one diagnostic line per
 * dead link, formatted for `PipelineSpecSyncGate::reportFailures()`.
 *
 * Extracted from `PipelineSpecSyncGate` per ticket #56 to keep the gate
 * class under PHPMD method-length / class-complexity thresholds.
 */
final readonly class PipelineSpecLinkResolver
{
    /**
     * Validate every internal markdown link resolves to a real file.
     *
     * @param string $relativePath Project-relative path of the source file (used in diagnostics).
     * @param string $sourceFile   Absolute path of the source file (used to resolve relative links).
     * @param string $body         Markdown body to scan.
     *
     * @return list<string>
     */
    public function validate(string $relativePath, string $sourceFile, string $body): array
    {
        $errors = [];

        foreach ($this->extractTargets($body) as $target) {
            $diagnostic = $this->diagnoseTarget($relativePath, $sourceFile, $target);

            if ($diagnostic === null) {
                continue;
            }

            $errors[] = $diagnostic;
        }

        return $errors;
    }

    /**
     * Return a diagnostic line for `$target`, or `null` when it resolves cleanly.
     *
     * @param string $relativePath Project-relative path of the source file (used in diagnostics).
     * @param string $sourceFile   Absolute path of the source file (used to resolve relative links).
     * @param string $target       Raw markdown link target.
     */
    private function diagnoseTarget(string $relativePath, string $sourceFile, string $target): ?string
    {
        if ($this->isSkippable($target)) {
            return null;
        }

        if (file_exists($this->resolveLink($sourceFile, $target))) {
            return null;
        }

        return sprintf('%s — dead-link: %s', $relativePath, $target);
    }

    /**
     * Extract the unique link targets from a markdown body.
     *
     * @param string $body Markdown body to scan.
     *
     * @return list<string>
     */
    private function extractTargets(string $body): array
    {
        $count = preg_match_all('/\\[[^\\]]+\\]\\(([^)\\s]+)\\)/', $body, $matches);

        if ($count === false || $count === 0) {
            return [];
        }

        /** @var list<non-empty-string> $captured */
        $captured = $matches[1];

        return array_values(array_unique($captured));
    }

    /**
     * Skip external links, in-page anchors, and template placeholders.
     *
     * @param string $target Markdown link target.
     */
    private function isSkippable(string $target): bool
    {
        if ($this->isExternalOrAnchor($target)) {
            return true;
        }

        return $this->isPlaceholder($target);
    }

    /**
     * External link or pure in-page anchor.
     *
     * @param string $target Markdown link target.
     */
    private function isExternalOrAnchor(string $target): bool
    {
        return str_starts_with($target, '#')
            || str_starts_with($target, 'http://')
            || str_starts_with($target, 'https://')
            || str_starts_with($target, 'mailto:')
            || str_starts_with($target, 'ftp://');
    }

    /**
     * Template placeholder like `<UUID — generate via …>`.
     *
     * @param string $target Markdown link target.
     */
    private function isPlaceholder(string $target): bool
    {
        return str_starts_with($target, '<') && str_ends_with($target, '>');
    }

    /**
     * Resolve a relative link against the source file's directory; strip any in-link anchor.
     *
     * @param string $sourceFile Absolute path of the file containing the link.
     * @param string $target     Raw markdown link target (may include `#anchor`).
     */
    private function resolveLink(string $sourceFile, string $target): string
    {
        // Anchors and external links are pre-filtered by `isSkippable()` in
        // the caller, so `$path` here is always non-empty.
        $hash = mb_strpos($target, '#');
        $path = $hash === false
            ? $target
            : substr($target, 0, $hash);

        $candidate = dirname($sourceFile) . '/' . $path;
        $real = realpath($candidate);

        return $real === false
            ? $candidate
            : $real;
    }
}
