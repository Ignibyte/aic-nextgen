<?php

declare(strict_types=1);

namespace Gates\Application;

use RuntimeException;

use function bin2hex;
use function count;
use function explode;
use function file_put_contents;
use function is_file;
use function ksort;
use function random_bytes;
use function rename;

/**
 * gate:21's cache file, `.native-gate/cache.txt`: one `FQCN key` line for every
 * runtime class whose last probe passed (`ClosureFingerprint`).
 *
 * A class whose key still matches is not probed again. The file is rewritten
 * after each run with the classes that passed in it, so a failure or a class
 * that is gone drops out; a file that cannot be read counts as empty, and a
 * line that is not two fields is skipped.
 */
final readonly class NativeProbeCache
{
    /**
     * The cache file's name in the work directory.
     */
    private const string FILE = 'cache.txt';

    /**
     * @param QuietFileReader $quietFileReader Reads the file without a warning when it is missing.
     */
    public function __construct(
        private QuietFileReader $quietFileReader = new QuietFileReader(),
    ) {
    }

    /**
     * The keys of the last run.
     *
     * @param string $workDir The gate's work directory.
     *
     * @return array<string, string> Key by class name; empty when there is no readable cache.
     */
    public function load(string $workDir): array
    {
        $path = $workDir . '/' . self::FILE;
        $text = is_file($path)
            ? $this->quietFileReader->read($path)
            : null;
        $keys = [];

        foreach (explode("\n", $text ?? '') as $line) {
            $fields = explode(' ', $line);

            if (count($fields) !== 2) {
                continue;
            }

            $keys[$fields[0]] = $fields[1];
        }

        return $keys;
    }

    /**
     * Replace the cache with this run's passing classes.
     *
     * @param string                $workDir The gate's work directory.
     * @param array<string, string> $keys    Key by class name.
     *
     * @throws RuntimeException When the file cannot be written.
     */
    public function save(string $workDir, array $keys): void
    {
        ksort($keys);
        $temporary = $workDir . '/' . self::FILE . '.' . bin2hex(random_bytes(8));
        $lines = '';

        foreach ($keys as $class => $key) {
            $lines .= $class . ' ' . $key . "\n";
        }

        if (file_put_contents($temporary, $lines) === false || !rename($temporary, $workDir . '/' . self::FILE)) {
            throw new RuntimeException('gate:21 — cannot write ' . $workDir . '/' . self::FILE);
        }
    }
}
