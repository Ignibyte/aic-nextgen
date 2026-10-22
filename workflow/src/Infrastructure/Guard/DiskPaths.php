<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure\Guard;

use AIC\Workflow\Application\Guard\PathResolver;
use Override;

use function array_pop;
use function basename;
use function dirname;
use function explode;
use function file_exists;
use function glob;
use function implode;
use function is_link;
use function preg_match;
use function readlink;
use function realpath;
use function str_starts_with;
use function substr;

/**
 * Paths on this machine (#102, Droost's `normalised_path` and
 * `resolved_relative`): made absolute without touching the disk, followed
 * through symlinks to where a write lands, and globs expanded.
 */
final readonly class DiskPaths implements PathResolver
{
    /**
     * @param string $home The user's home, for `~/`; empty when unknown.
     */
    public function __construct(
        private string $home,
    ) {
    }

    /**
     * @param string $path A path as written.
     * @param string $cwd  Where it is relative to.
     *
     * @return string It absolute and normalised.
     */
    #[Override]
    public function absolute(string $path, string $cwd): string
    {
        $full = match (true) {
            str_starts_with($path, '/') => $path,
            ($path === '~' || str_starts_with($path, '~/')) && $this->home !== '' => $this->home . substr($path, 1),
            default => $cwd . '/' . $path,
        };
        $parts = [];

        foreach (explode('/', $full) as $part) {
            $parts = match ($part) {
                '', '.' => $parts,
                '..' => $this->parent($parts),
                default => [...$parts, $part],
            };
        }

        return '/' . implode('/', $parts);
    }

    /**
     * @param string $absolute An absolute path.
     *
     * @return string Where a write to it lands.
     */
    #[Override]
    public function landed(string $absolute): string
    {
        $path = $this->followed($absolute);
        $rest = '';

        while ($path !== '/' && !file_exists($path)) {
            $rest = '/' . basename($path) . $rest;
            $path = dirname($path);
        }

        $real = realpath($path);

        return ($real === false || $real === '/' ? '' : $real) . $rest;
    }

    /**
     * @param string $pattern A word.
     * @param string $cwd     Where it is relative to.
     *
     * @return list<string> Its matches, or itself, absolute.
     */
    #[Override]
    public function expand(string $pattern, string $cwd): array
    {
        $absolute = $this->absolute($pattern, $cwd);

        if (preg_match('/[*?[]/', $pattern) !== 1) {
            return [$absolute];
        }

        $matches = glob($absolute);

        return $matches === false || $matches === []
            ? [$absolute]
            : $matches;
    }

    /**
     * @param string $absolute An absolute path.
     *
     * @return string It with a symlink at its end followed, eight hops at most.
     */
    private function followed(string $absolute): string
    {
        $path = $absolute;

        for ($hops = 0; $hops < 8 && is_link($path); $hops += 1) {
            $path = $this->absolute((string) readlink($path), dirname($path));
        }

        return $path;
    }

    /**
     * @param list<string> $parts The path so far.
     *
     * @return list<string> It one directory up.
     */
    private function parent(array $parts): array
    {
        array_pop($parts);

        return $parts;
    }
}
