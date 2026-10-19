<?php

declare(strict_types=1);

namespace Gates\Application;

use JsonException;

use function file_get_contents;
use function is_array;
use function is_file;
use function is_readable;
use function is_string;
use function json_decode;

use const JSON_THROW_ON_ERROR;

/**
 * Loads the union of:
 *   1. The hardcoded `ALWAYS_OK` list — PHP-core / SPL / standard-extension
 *      classes that ship with every PHP 8.5 install, so manifests don't have
 *      to declare phantom `ext-spl` / `ext-standard` requires.
 *   2. The project-level allowlist in `.composer-require-checker.json`'s
 *      `symbol-whitelist` field. Missing or unreadable files silently fall
 *      back to `ALWAYS_OK`.
 *
 * @api
 */
final readonly class RequireCheckerAllowlist
{
    /**
     * PHP core / SPL / standard-extension classes that are always covered.
     */
    public const array ALWAYS_OK_FQNS = [
        'BackedEnum',
        'UnitEnum',
        'Closure',
        'Generator',
        'Throwable',
        'Stringable',
        'Override',
        'Iterator',
        'IteratorAggregate',
        'ArrayAccess',
        'Countable',
        'Traversable',
        'WeakMap',
        'WeakReference',
        'Exception',
        'RuntimeException',
        'LogicException',
        'InvalidArgumentException',
        'JsonException',
        'OutOfBoundsException',
        'OutOfRangeException',
        'UnexpectedValueException',
        'TypeError',
        'ValueError',
        'Error',
        'UnhandledMatchError',
        'DirectoryIterator',
        'RecursiveIteratorIterator',
        'RecursiveDirectoryIterator',
        'RecursiveCallbackFilterIterator',
        'RegexIterator',
        'FilesystemIterator',
        'GlobIterator',
        'SplFileInfo',
        'SplObjectStorage',
        'SplStack',
        'SplQueue',
        'DateTime',
        'DateTimeImmutable',
        'DateTimeInterface',
        'DateInterval',
        'DateTimeZone',
        'ReflectionClass',
        'ReflectionMethod',
        'ReflectionProperty',
        'ReflectionParameter',
        'ReflectionException',
        'ReflectionExtension',
        'ReflectionFunction',
        'ReflectionNamedType',
        'ReflectionType',
        'ReflectionUnionType',
        'ReflectionIntersectionType',
        'SimpleXMLElement',
        'Attribute',
        'SensitiveParameter',
    ];

    /**
     * Build the merged allowlist for `$root`'s project.
     *
     * @param string $root Repo-root absolute path.
     *
     * @throws JsonException When `.composer-require-checker.json` is malformed.
     *
     * @return list<string>
     */
    public function load(string $root): array
    {
        $allowlistedFqns = self::ALWAYS_OK_FQNS;

        $projectFile = $root . '/.composer-require-checker.json';

        if (!is_file($projectFile) || !is_readable($projectFile)) {
            return $allowlistedFqns;
        }

        $raw = file_get_contents($projectFile);

        return is_string($raw)
            ? [...$allowlistedFqns, ...$this->parseSymbolWhitelist($raw)]
            : $allowlistedFqns;
    }

    /**
     * Decode the project file and return its `symbol-whitelist` as a `list<string>`.
     *
     * @param string $raw Raw `.composer-require-checker.json` contents.
     *
     * @throws JsonException When the file is not valid JSON.
     *
     * @return list<string>
     */
    private function parseSymbolWhitelist(string $raw): array
    {
        $decoded = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
        $entries = is_array($decoded)
            ? ($decoded['symbol-whitelist'] ?? null)
            : null;

        if (!is_array($entries)) {
            return [];
        }

        $whitelist = [];

        foreach ($entries as $entry) {
            if (!is_string($entry)) {
                continue;
            }

            $whitelist[] = $entry;
        }

        return $whitelist;
    }
}
