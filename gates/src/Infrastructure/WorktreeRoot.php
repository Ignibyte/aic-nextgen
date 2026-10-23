<?php

declare(strict_types=1);

namespace Gates\Infrastructure;

use Gates\Domain\RegexCaptures;

use function array_key_exists;
use function dirname;
use function file_get_contents;
use function is_file;
use function realpath;
use function str_starts_with;
use function trim;

/**
 * Resolves a worktree's shared hook directory, retaining the supplied root for unavailable metadata.
 */
final readonly class WorktreeRoot
{
    use RegexCaptures;

    /**
     * @param string $root The working checkout's root.
     *
     * @return string The canonical checkout's root, or the supplied root when it cannot be resolved.
     */
    public function path(string $root): string
    {
        if (!is_file($root . '/.git')) {
            return $root;
        }

        $matches = self::regexMatch('/^gitdir:\\s*(.+)$/m', (string) file_get_contents($root . '/.git'));

        if (!array_key_exists(1, $matches)) {
            return $root;
        }

        $gitDir = self::relative($root, trim($matches[1]));
        $commonDirFile = $gitDir . '/commondir';

        if (!is_file($commonDirFile)) {
            return $root;
        }

        $canonical = realpath(self::relative($gitDir, trim((string) file_get_contents($commonDirFile))));

        return $canonical === false
            ? $root
            : dirname($canonical);
    }

    /**
     * @param string $base The metadata's containing directory.
     * @param string $path An absolute or relative path.
     *
     * @return string The absolute path as written by the metadata.
     */
    private static function relative(string $base, string $path): string
    {
        return str_starts_with($path, '/')
            ? $path
            : $base . '/' . $path;
    }
}
