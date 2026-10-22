<?php

declare(strict_types=1);

namespace Gates\Application;

use function array_filter;
use function array_key_exists;
use function array_keys;
use function array_values;
use function file_get_contents;
use function glob;
use function implode;
use function is_file;
use function strlen;
use function substr;

use const ARRAY_FILTER_USE_BOTH;

/**
 * Compares the build's outputs with the committed files (`bin/aic-build`,
 * gate:24): which differ, which a generated directory holds that no output
 * writes, and how to name them in a message.
 */
final readonly class BuildOutputs
{
    /**
     * @param string $root The repo root, which the names in a message are relative to.
     */
    public function __construct(
        private string $root,
    ) {
    }

    /**
     * @param array<string, string> $outputs Each output's content by absolute path.
     *
     * @return list<string> The paths whose committed content differs, a missing file included.
     */
    public function stale(array $outputs): array
    {
        return array_keys(array_filter(
            $outputs,
            static fn (string $output, string $file): bool => (is_file($file) ? file_get_contents($file) : '') !== $output,
            ARRAY_FILTER_USE_BOTH,
        ));
    }

    /**
     * The files in a generated directory that no output writes: what a removed
     * module left behind. The build owns the directory, so they go.
     *
     * @param string                $directory A directory the build generates, absolute.
     * @param array<string, string> $outputs   Each output's content by absolute path.
     * @param string                $pattern   The files the build owns there, `*.php` or the wiki's `*.md` (#108).
     *
     * @return list<string> The matching files in the directory that are not outputs.
     */
    public function orphans(string $directory, array $outputs, string $pattern = '*.php'): array
    {
        $files = glob($directory . '/' . $pattern);

        return array_values(array_filter(
            $files === false ? [] : $files,
            static fn (string $file): bool => !array_key_exists($file, $outputs),
        ));
    }

    /**
     * @param list<string> $files Absolute paths under the root.
     *
     * @return string The paths relative to the root, comma-separated.
     */
    public function names(array $files): string
    {
        $names = [];

        foreach ($files as $file) {
            $names[] = substr($file, strlen($this->root) + 1);
        }

        return implode(', ', $names);
    }
}
