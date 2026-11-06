<?php

declare(strict_types=1);

namespace Gates\Application;

use JsonException;

use function array_keys;
use function file_get_contents;
use function fwrite;
use function is_array;
use function is_file;
use function is_string;
use function json_decode;
use function restore_error_handler;
use function set_error_handler;
use function sprintf;

use const JSON_THROW_ON_ERROR;
use const PHP_EOL;

/**
 * Reads `vendor/composer/installed.json` and builds the many-to-many
 * `psr-4-prefix → list<composer-package>` map the require-checker uses
 * to decide whether a manifest's declared requires cover a used FQN.
 *
 * Multiple packages can own the same prefix in the wild (real case:
 * `psr/http-message` and `psr/http-factory` both register
 * `Psr\Http\Message\`).
 *
 * A platform package Composer provides is in no `installed.json` entry, yet
 * it backs the namespace it is the API of: `composer-plugin-api` owns
 * `Composer\`, for a Composer plugin's manifest (#279).
 *
 * @phpstan-type InstalledPackage array{name?: string, autoload?: array{psr-4?: array<string, string|list<string>>}}
 */
final readonly class InstalledPackageMap
{
    /**
     * Each platform package that backs a namespace, and its prefixes.
     */
    private const array PLATFORM_PREFIXES = [
        'composer-plugin-api' => ['Composer\\'],
    ];

    /**
     * Build the prefix → owners map for `$root`. Returns `null` when
     * `installed.json` is missing or unreadable.
     *
     * @param string   $root   Repo-root absolute path.
     * @param resource $stderr Stream failure messages are written to.
     *
     * @throws JsonException When `installed.json` is not valid JSON.
     *
     * @return array<string, list<string>>|null
     */
    public function build(string $root, mixed $stderr): ?array
    {
        $installedJson = $root . '/vendor/composer/installed.json';

        if (!is_file($installedJson)) {
            fwrite($stderr, 'gate:3 — vendor/composer/installed.json not found. Run `composer install`.' . PHP_EOL);

            return null;
        }

        set_error_handler(static fn (): bool => true);

        try {
            $raw = file_get_contents($installedJson);
        } finally {
            restore_error_handler();
        }

        if ($raw === false) {
            fwrite($stderr, sprintf('gate:3 — cannot read %s.%s', $installedJson, PHP_EOL));

            return null;
        }

        return $this->buildFromRaw($raw);
    }

    /**
     * Build the map from raw `installed.json` JSON. Internal helper exposed
     * for unit tests that already hold the document text.
     *
     * @param string $raw Raw `installed.json` JSON.
     *
     * @throws JsonException When the JSON is malformed.
     *
     * @return array<string, list<string>>
     */
    private function buildFromRaw(string $raw): array
    {
        /** @var array{packages?: list<InstalledPackage>} $decoded */
        $decoded = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);

        $packages = $decoded['packages'] ?? [];
        $map = [];

        foreach (self::PLATFORM_PREFIXES as $platform => $prefixes) {
            $map = $this->mergePrefixes($map, $platform, $prefixes);
        }

        foreach ($packages as $package) {
            $map = $this->indexPackage($map, $package);
        }

        return $map;
    }

    /**
     * Index every PSR-4 prefix this package owns into the running map.
     *
     * @param array<string, list<string>> $map     Running prefix → owners map.
     * @param array                       $package One package entry from `installed.json`.
     *
     * @phpstan-param InstalledPackage $package
     *
     * @return array<string, list<string>>
     */
    private function indexPackage(array $map, array $package): array
    {
        $name = $package['name'] ?? null;

        if (!is_string($name)) {
            return $map;
        }

        $psr4 = $package['autoload']['psr-4'] ?? null;

        if (!is_array($psr4)) {
            return $map;
        }

        return $this->mergePrefixes($map, $name, array_keys($psr4));
    }

    /**
     * Merge each non-empty prefix from `$prefixes` into `$map` under `$name`.
     *
     * @param array<string, list<string>> $map      Running prefix → owners map.
     * @param string                      $name     Composer package name.
     * @param list<string>                $prefixes PSR-4 prefix keys for this package.
     *
     * @return array<string, list<string>>
     */
    private function mergePrefixes(array $map, string $name, array $prefixes): array
    {
        foreach ($prefixes as $prefix) {
            if ($prefix === '') {
                continue;
            }

            $map[$prefix] ??= [];
            $map[$prefix][] = $name;
        }

        return $map;
    }
}
