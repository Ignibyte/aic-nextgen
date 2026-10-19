<?php

declare(strict_types=1);

namespace Gates\Application;

use FilesystemIterator;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function in_array;
use function mb_strtolower;
use function sort;
use function str_replace;

/**
 * Lists the markdown files under a project tree, for the documentation gates
 * (gate:19 and gate:22).
 *
 * Directories are skipped by name (vendor, caches) or by their path under the
 * root (the private `docs/planning`), so a gate never descends into them.
 *
 * @api
 */
final readonly class MarkdownTreeWalker
{
    /**
     * Every `*.md` file under `$root`, sorted, outside the skipped directories.
     *
     * @param string       $root      Repo-root absolute path.
     * @param list<string> $skipDirs  Directory names skipped wherever they occur.
     * @param list<string> $skipPaths Directory paths, relative to `$root`, skipped by location.
     *
     * @return list<string> Absolute paths.
     */
    public function markdownFiles(string $root, array $skipDirs, array $skipPaths = []): array
    {
        $files = [];

        foreach ($this->iterate($root, $skipDirs, $skipPaths) as $entry) {
            if (!$entry->isFile() || mb_strtolower($entry->getExtension()) !== 'md') {
                continue;
            }

            $files[] = $entry->getPathname();
        }

        sort($files);

        return $files;
    }

    /**
     * Walk the tree under `$root`, pruning the skipped directories.
     *
     * @param string       $root      Repo-root absolute path.
     * @param list<string> $skipDirs  Directory names skipped wherever they occur.
     * @param list<string> $skipPaths Directory paths, relative to `$root`, skipped by location.
     *
     * @return iterable<SplFileInfo> Every entry outside the skipped directories.
     */
    private function iterate(string $root, array $skipDirs, array $skipPaths): iterable
    {
        return new RecursiveIteratorIterator(
            new RecursiveCallbackFilterIterator(
                new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
                static fn (SplFileInfo $current): bool => !$current->isDir()
                    || !self::isSkipped($current, $root, $skipDirs, $skipPaths),
            ),
        );
    }

    /**
     * Whether a directory is skipped, by name or by its path under `$root`.
     *
     * @param SplFileInfo  $dir       Directory entry.
     * @param string       $root      Repo-root absolute path.
     * @param list<string> $skipDirs  Directory names skipped wherever they occur.
     * @param list<string> $skipPaths Directory paths, relative to `$root`, skipped by location.
     *
     * @return bool True to skip the directory.
     */
    private static function isSkipped(SplFileInfo $dir, string $root, array $skipDirs, array $skipPaths): bool
    {
        return in_array($dir->getFilename(), $skipDirs, true)
            || in_array(str_replace($root . '/', '', $dir->getPathname()), $skipPaths, true);
    }
}
