<?php

declare(strict_types=1);

namespace Gates\Application;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function is_dir;
use function str_ends_with;

/**
 * Recursive `*.php` directory utilities shared by multiple gate scripts.
 *
 * Centralises the "does this directory contain at least one php file?"
 * predicate so the per-gate classes (DeduplicatorGate,
 * PhpSourceCollector, …) do not duplicate the iterator scaffolding.
 *
 * @api
 */
final readonly class PhpFileFinder
{
    /**
     * Decide whether `$path` exists and recursively contains at least one
     * `.php` file.
     *
     * @param string $path Absolute path to test.
     */
    public function containsPhpFiles(string $path): bool
    {
        if (!is_dir($path)) {
            return false;
        }

        foreach ($this->iterate($path) as $file) {
            if ($this->isPhpFile($file)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Build a recursive iterator rooted at `$dir`.
     *
     * @param string $dir Directory absolute path.
     *
     * @return iterable<SplFileInfo>
     */
    private function iterate(string $dir): iterable
    {
        return new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        );
    }

    /**
     * Decide whether `$file` is a regular `*.php` source file.
     *
     * @param SplFileInfo $file Iterator entry to inspect.
     */
    private function isPhpFile(SplFileInfo $file): bool
    {
        return $file->isFile() && str_ends_with($file->getFilename(), '.php');
    }
}
