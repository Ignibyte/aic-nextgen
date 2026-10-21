<?php

declare(strict_types=1);

namespace Gates\Application;

use RuntimeException;

use function hash;
use function hash_file;
use function implode;

/**
 * The keys of gate:21's cache: what a probe compiled, run and compared, as one hash.
 *
 * A class's key covers its closure, each file by path and content, on top of
 * the toolchain's: the compiler and the PHP oracle (`NativeToolchain::identity()`),
 * the pinned native packages and the probe program. Two runs that agree on the
 * key would probe the same input with the same tools, so a key that matched a
 * green probe can stand in for it. xxh128 is enough: the key guards against
 * change, not tampering.
 */
final readonly class ClosureFingerprint
{
    /**
     * The algorithm every key and file hash uses.
     */
    private const string ALGORITHM = 'xxh128';

    /**
     * The toolchain's part of every key.
     *
     * @param string       $identity     `NativeToolchain::identity()`.
     * @param list<string> $packageFiles The pinned `elephc.toml` and `elephc.lock`.
     * @param string       $program      The probe program every class runs under.
     *
     * @throws RuntimeException When a package file cannot be read.
     *
     * @return string The hash.
     */
    public function toolchain(string $identity, array $packageFiles, string $program): string
    {
        return $this->digest($identity . "\n" . $program, $packageFiles);
    }

    /**
     * One class's key: the toolchain's, then each file of its closure in load order.
     *
     * @param string       $toolchain `toolchain()`.
     * @param list<string> $files     The class's closure, dependencies first.
     *
     * @throws RuntimeException When a file cannot be read.
     *
     * @return string The hash.
     */
    public function closure(string $toolchain, array $files): string
    {
        return $this->digest($toolchain, $files);
    }

    /**
     * Hash a head line and each file by path and content, in order.
     *
     * @param string       $head  What the files are hashed on top of.
     * @param list<string> $files Files, in order.
     *
     * @throws RuntimeException When a file cannot be read.
     *
     * @return string The hash.
     */
    private function digest(string $head, array $files): string
    {
        $parts = [$head];

        foreach ($files as $file) {
            $parts[] = $file . "\0" . $this->fileHash($file);
        }

        return hash(self::ALGORITHM, implode("\n", $parts));
    }

    /**
     * @param string $file A file to hash.
     *
     * @throws RuntimeException When it cannot be read.
     *
     * @return string Its content hash.
     */
    private function fileHash(string $file): string
    {
        $hash = hash_file(self::ALGORITHM, $file);

        if ($hash === false) {
            throw new RuntimeException('gate:21 — cannot read ' . $file);
        }

        return $hash;
    }
}
