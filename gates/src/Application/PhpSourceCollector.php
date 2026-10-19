<?php

declare(strict_types=1);

namespace Gates\Application;

use FilesystemIterator;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function array_merge;
use function is_dir;
use function is_string;
use function mb_trim;
use function sort;

/**
 * Walks a manifest's `autoload.psr-4` paths recursively and returns
 * every PHP file found.
 *
 * `classmap` and `files` autoload entries are ignored — gate:3 is
 * scoped to PSR-4 source like the rest of the AIC monorepo.
 */
final readonly class PhpSourceCollector
{
    /**
     * Collect every `*.php` file reachable through the manifest's PSR-4 prefixes.
     *
     * @param string                             $manifestDir Absolute path to the manifest's directory.
     * @param array<string, string|list<string>> $psr4        Manifest's `autoload.psr-4` block.
     *
     * @return list<string>
     */
    public function collect(string $manifestDir, array $psr4): array
    {
        $files = [];

        foreach ($psr4 as $relPath) {
            $files = [...$files, ...$this->collectFromAutoloadEntry($manifestDir, $relPath)];
        }

        sort($files);

        return $files;
    }

    /**
     * Resolve a single PSR-4 autoload-entry value (string or list-of-strings)
     * into the list of `*.php` files reachable under it.
     *
     * @param string              $manifestDir Absolute path to the manifest's directory.
     * @param string|list<string> $relPath     Autoload-entry value (string or list).
     *
     * @return list<string>
     */
    private function collectFromAutoloadEntry(string $manifestDir, string|array $relPath): array
    {
        if (is_string($relPath)) {
            return $this->listPhpUnder($manifestDir . '/' . mb_trim($relPath, '/'));
        }

        $perEntry = [];

        foreach ($relPath as $entry) {
            $perEntry[] = $this->listPhpUnder($manifestDir . '/' . mb_trim($entry, '/'));
        }

        return array_merge(...$perEntry);
    }

    /**
     * Recursively list every `*.php` file under `$abs`.
     *
     * @param string $abs Absolute path to a candidate directory.
     *
     * @return list<string>
     */
    private function listPhpUnder(string $abs): array
    {
        if (!is_dir($abs)) {
            return [];
        }

        $files = [];

        foreach ($this->iterateDirectory($abs) as $file) {
            if (!$this->isPhpFile($file)) {
                continue;
            }

            $files[] = $file->getPathname();
        }

        return $files;
    }

    /**
     * Decide whether `$file` is a regular `*.php` source file.
     *
     * @param SplFileInfo $file File-info entry produced by the recursive iterator.
     */
    private function isPhpFile(SplFileInfo $file): bool
    {
        return $file->isFile() && $file->getExtension() === 'php';
    }

    /**
     * Build a recursive iterator rooted at `$dir`, pruning any `tests/`
     * directory. Test-only code is a require-dev concern and must never be
     * require-checked as source, even when it sits under an autoload root.
     *
     * @param string $dir Directory absolute path.
     *
     * @return iterable<SplFileInfo>
     */
    private function iterateDirectory(string $dir): iterable
    {
        return new RecursiveIteratorIterator(
            new RecursiveCallbackFilterIterator(
                new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
                static fn (SplFileInfo $entry): bool => $entry->getFilename() !== 'tests',
            ),
        );
    }
}
