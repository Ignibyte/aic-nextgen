<?php

declare(strict_types=1);

namespace Gates\Application;

use InvalidArgumentException;

use function array_filter;
use function array_key_exists;
use function array_keys;
use function array_values;
use function basename;
use function explode;
use function file_get_contents;
use function fnmatch;
use function glob;
use function implode;
use function in_array;
use function is_file;
use function is_link;
use function sort;
use function str_starts_with;
use function strlen;
use function substr;

use const ARRAY_FILTER_USE_BOTH;

/**
 * Compares the build's outputs with the committed files (`aic-build`,
 * gate:24): which differ, which a generated directory holds that no output
 * writes, and how to name them in a message.
 */
final readonly class BuildOutputs
{
    /**
     * @param string     $root       The repo root, which the names in a message are relative to.
     * @param FileLister $fileLister Audits nested generated output folders.
     */
    public function __construct(
        private string $root,
        private FileLister $fileLister = new FileLister(),
    ) {
    }

    /**
     * @param array<string, string> $outputs Each output's content by absolute path.
     *
     * @throws InvalidArgumentException When an output escapes the root or traverses a symlink.
     *
     * @return list<string> The paths whose committed content differs, a missing file included.
     */
    public function stale(array $outputs): array
    {
        foreach (array_keys($outputs) as $file) {
            $this->owned($file);
        }

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
     * @param bool                  $recursive Whether to audit every nested output folder.
     *
     * @throws InvalidArgumentException When the directory escapes the project or traverses a symlink.
     *
     * @return list<string> The matching files in the directory that are not outputs.
     */
    public function orphans(
        string $directory,
        array $outputs,
        string $pattern = '*.php',
        bool $recursive = false,
    ): array {
        $this->owned($directory);
        $files = $recursive
            ? $this->fileLister->files($directory)
            : glob($directory . '/' . $pattern);

        $orphans = array_values(array_filter(
            $files === false ? [] : $files,
            static fn (string $file): bool => fnmatch($pattern, basename($file)) && !array_key_exists($file, $outputs),
        ));
        sort($orphans);

        return $orphans;
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

    /**
     * @param string $file One planned output or output folder.
     *
     * @throws InvalidArgumentException When it is outside the project or traverses a symlink.
     */
    private function owned(string $file): void
    {
        if (!str_starts_with($file, $this->root . '/')) {
            throw new InvalidArgumentException($file . ': build output must be inside the project.');
        }

        $path = $this->root;

        foreach (explode('/', substr($file, strlen($this->root) + 1)) as $segment) {
            $this->segment($segment, $file);

            $path .= '/' . $segment;

            if (is_link($path)) {
                throw new InvalidArgumentException($file . ': build output cannot traverse a symlink.');
            }
        }
    }

    /**
     * @param string $segment One output path segment.
     * @param string $file    The complete path for a refusal.
     *
     * @throws InvalidArgumentException When a segment is empty or traverses ancestors.
     */
    private function segment(string $segment, string $file): void
    {
        if (in_array($segment, ['', '.', '..'], true)) {
            throw new InvalidArgumentException($file . ': build output must be canonical.');
        }
    }
}
