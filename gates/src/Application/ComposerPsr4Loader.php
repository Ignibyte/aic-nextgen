<?php

declare(strict_types=1);

namespace Gates\Application;

use Gates\Domain\Psr4Map;
use RuntimeException;

use function array_is_list;
use function is_array;
use function is_file;
use function is_string;

/**
 * Loads composer's PSR-4 map (`vendor/composer/autoload_psr4.php`) into a {@see Psr4Map}.
 *
 * The file is PHP that returns an array, so it is loaded with `require`; the
 * gate tooling runs interpreted, outside the native corpus.
 */
final readonly class ComposerPsr4Loader
{
    /**
     * Load the PSR-4 map of the project at `$root`.
     *
     * @param string $root Repo-root absolute path.
     *
     * @throws RuntimeException When the file is missing or does not return a prefix map.
     *
     * @return Psr4Map The loaded map.
     */
    public function load(string $root): Psr4Map
    {
        $file = $root . '/vendor/composer/autoload_psr4.php';

        if (!is_file($file)) {
            throw new RuntimeException($file . ' not found; run composer install.');
        }

        /** @var array<array-key, array<array-key, string>|string> $raw */
        $raw = require $file;

        return new Psr4Map($this->prefixMap($raw, $file));
    }

    /**
     * Check the loaded array's shape: string prefix => list of directories.
     *
     * @param array<array-key, array<array-key, string>|string> $raw  What the file returned.
     * @param string                                            $file The file, for the message.
     *
     * @throws RuntimeException When an entry has another shape.
     *
     * @return array<string, list<string>> The prefix map.
     */
    private function prefixMap(array $raw, string $file): array
    {
        $prefixes = [];

        foreach ($raw as $prefix => $directories) {
            if (!is_string($prefix) || !is_array($directories) || !array_is_list($directories)) {
                throw new RuntimeException($file . ' does not return a PSR-4 prefix map.');
            }

            $prefixes[$prefix] = $directories;
        }

        return $prefixes;
    }
}
