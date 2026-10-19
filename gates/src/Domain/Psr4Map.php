<?php

declare(strict_types=1);

namespace Gates\Domain;

use function is_file;
use function realpath;
use function str_replace;
use function str_starts_with;
use function strlen;
use function substr;
use function uksort;

/**
 * Composer's PSR-4 prefixes, resolving a class name to its source file.
 *
 * Read from `vendor/composer/autoload_psr4.php`, which composer keeps current
 * as files are added (a classmap needs a dump). Longer prefixes win, so
 * `Gates\Architecture\` beats `Gates\`. Paths come back resolved, so the
 * `vendor/aic/framework` symlink leads to `framework/src`.
 *
 * @api
 */
final readonly class Psr4Map
{
    /**
     * @param array<string, list<string>> $prefixes Namespace prefix (with trailing `\`) => base directories.
     */
    public function __construct(private array $prefixes)
    {
        uksort($prefixes, static fn (string $left, string $right): int => strlen($right) <=> strlen($left));
    }

    /**
     * Resolve a fully-qualified class name to its source file.
     *
     * @param string $fqcn Class name without a leading `\`.
     *
     * @return string|null The resolved absolute path, or null when no prefix maps it to a file.
     */
    public function fileFor(string $fqcn): ?string
    {
        foreach ($this->prefixes as $prefix => $directories) {
            if (!str_starts_with($fqcn, $prefix)) {
                continue;
            }

            $file = $this->fileUnder(str_replace('\\', '/', substr($fqcn, strlen($prefix))) . '.php', $directories);

            if ($file !== null) {
                return $file;
            }
        }

        return null;
    }

    /**
     * The first base directory that holds the relative path, resolved.
     *
     * @param string       $relative    Path below the prefix's base directory.
     * @param list<string> $directories The prefix's base directories.
     *
     * @return string|null The resolved absolute path, or null when no directory holds it.
     */
    private function fileUnder(string $relative, array $directories): ?string
    {
        foreach ($directories as $directory) {
            $path = $directory . '/' . $relative;

            if (!is_file($path)) {
                continue;
            }

            return $this->resolved($path);
        }

        return null;
    }

    /**
     * The path with symlinks resolved, or as given when it cannot be resolved.
     *
     * @param string $path An existing file path.
     *
     * @return string The resolved path.
     */
    private function resolved(string $path): string
    {
        $resolved = realpath($path);

        return $resolved === false
            ? $path
            : $resolved;
    }
}
