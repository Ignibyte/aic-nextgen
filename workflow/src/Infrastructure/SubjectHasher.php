<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use AIC\Workflow\Application\SubjectHashing;
use Override;

use function hash;
use function hash_file;
use function implode;
use function is_dir;
use function is_file;
use function is_link;
use function readlink;
use function sprintf;
use function stat;
use function str_contains;
use function str_starts_with;

/**
 * Hashes a verdict's subject (#101), in the scheme gate:21's closure
 * fingerprint uses: xxh128 over `path NUL hash` lines, the paths sorted by
 * the caller. A symlink is hashed by its target, a deleted path as deleted,
 * and a path that leaves the project is refused as outside. Toolchain files
 * are keyed by their stat, as gate:21 keys its compiler.
 */
final readonly class SubjectHasher implements SubjectHashing
{
    /**
     * @param string $root The project's root.
     */
    public function __construct(
        private string $root,
    ) {
    }

    /**
     * @param list<string> $paths     Paths in the project.
     * @param list<string> $statPaths Files keyed by stat.
     *
     * @return string The subject hash.
     */
    #[Override]
    public function hash(array $paths, array $statPaths): string
    {
        $lines = [];

        foreach ($paths as $path) {
            $lines[] = $path . "\0" . $this->content($path);
        }

        foreach ($statPaths as $statPath) {
            $stat = stat($statPath);
            $lines[] = $stat === false
                ? $statPath . ' missing'
                : sprintf('%s %d %d %d', $statPath, $stat['size'], $stat['mtime'], $stat['ino']);
        }

        return hash('xxh128', implode("\n", $lines));
    }

    /**
     * @param string $path A path in the project.
     *
     * @return string Its content's hash, or what stands for it.
     */
    private function content(string $path): string
    {
        $file = $this->root . '/' . $path;

        return match (true) {
            str_starts_with($path, '/') || str_contains('/' . $path . '/', '/../') => 'outside',
            is_link($file) => self::link($file),
            is_dir($file) => 'directory',
            is_file($file) => (string) hash_file('xxh128', $file),
            default => 'deleted',
        };
    }

    /**
     * @param string $file A symbolic link.
     *
     * @return string Its target fingerprint, or a distinct failed-read marker.
     */
    private static function link(string $file): string
    {
        $target = readlink($file);

        return $target === false
            ? 'unreadable link'
            : 'link ' . $target;
    }
}
