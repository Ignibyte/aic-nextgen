<?php

declare(strict_types=1);

namespace Gates\Infrastructure;

use Gates\Domain\ScaffoldCache;
use Override;

use function file;
use function file_put_contents;
use function in_array;
use function is_dir;
use function is_file;
use function mkdir;

use const FILE_APPEND;
use const FILE_IGNORE_NEW_LINES;
use const FILE_SKIP_EMPTY_LINES;

/**
 * gate:33's cache (#300) in `.native-gate/scaffold-cache.txt`, one
 * `<kind> <key>` line per pass, beside gate:21's own cache.
 */
final readonly class ScaffoldCacheFile implements ScaffoldCache
{
    /**
     * @param string $root The project's root.
     */
    public function __construct(
        private string $root,
    ) {
    }

    /**
     * @param string $kind A kind.
     * @param string $key  The hash of its folder and the judging gates.
     *
     * @return bool Whether that kind passed with that key.
     */
    #[Override]
    public function passed(string $kind, string $key): bool
    {
        $path = $this->root . '/.native-gate/scaffold-cache.txt';
        $lines = is_file($path)
            ? file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)
            : false;

        return in_array($kind . ' ' . $key, $lines === false ? [] : $lines, true);
    }

    /**
     * @param string $kind A kind that passed.
     * @param string $key  Its key.
     */
    #[Override]
    public function record(string $kind, string $key): void
    {
        $folder = $this->root . '/.native-gate';

        if (!is_dir($folder)) {
            mkdir($folder, 0o755, true);
        }

        file_put_contents($folder . '/scaffold-cache.txt', $kind . ' ' . $key . "\n", FILE_APPEND);
    }
}
