<?php

declare(strict_types=1);

namespace Gates\Application;

use InvalidArgumentException;
use stdClass;

use function array_filter;
use function array_key_exists;
use function array_keys;
use function array_map;
use function array_values;
use function get_object_vars;
use function is_array;
use function is_string;
use function json_decode;
use function preg_replace_callback;

/**
 * The published assets a gate:23 step may name (#251): `{asset:<name>}` in a
 * step's path or form stands for that asset's alias path in the build's
 * private catalog, `build/assets.json` (`{asset:tessera/tessera.csp.iife.js}`
 * is `/assets/tessera-0.4.2/tessera.csp.iife.js` while 0.4.2 is pinned), so a
 * Tessera pin bump changes `config/app.php` and no step.
 */
final readonly class NativeSiteAssets
{
    /**
     * The catalog, relative to the project's root.
     */
    public const string CATALOG = 'build/assets.json';

    /**
     * A placeholder: `{asset:` and a name of no brace or space, then `}`.
     */
    private const string PLACEHOLDER = '/\\{asset:([^{}\\s]+)\\}/';

    /**
     * @param QuietFileReader $quietFileReader Reads the catalog.
     */
    public function __construct(
        private QuietFileReader $quietFileReader = new QuietFileReader(),
    ) {
    }

    /**
     * @param string $root The project's root.
     *
     * @return array<string, string> Each published asset's alias path, by name; none when the catalog is missing or
     *                               unreadable, so any placeholder is then refused.
     */
    public function aliases(string $root): array
    {
        $aliases = [];

        foreach ($this->entries($root) as $entry) {
            $aliases = [...$aliases, ...$this->alias($entry)];
        }

        return $aliases;
    }

    /**
     * @param string                $text    A step's path or form.
     * @param array<string, string> $aliases The alias paths, by name.
     * @param string                $where   The step and field, for the refusal.
     *
     * @throws InvalidArgumentException When a placeholder names an asset the catalog does not list.
     *
     * @return string The text, each placeholder replaced by its asset's alias path.
     */
    public function resolve(string $text, array $aliases, string $where): string
    {
        return preg_replace_callback(
            self::PLACEHOLDER,
            static fn (array $match): string => self::aliasOf(self::named($match), $aliases, $where),
            $text,
        ) ?? $text;
    }

    /**
     * @param string $root The project's root.
     *
     * @return list<stdClass> The catalog's asset entries; none when it is missing or unreadable.
     */
    private function entries(string $root): array
    {
        $decoded = [json_decode($this->quietFileReader->read($root . '/' . self::CATALOG) ?? '')];
        $fields = $decoded[0] instanceof stdClass
            ? get_object_vars($decoded[0])
            : [];

        if (!array_key_exists('assets', $fields) || !is_array($fields['assets'])) {
            return [];
        }

        $assets = $fields['assets'];

        return array_values(array_filter(array_map(
            static fn (int|string $key): ?stdClass => $assets[$key] instanceof stdClass ? $assets[$key] : null,
            array_keys($assets),
        )));
    }

    /**
     * @param stdClass $entry One catalog entry.
     *
     * @return array<string, string> Its alias path by its name; none when it lacks either.
     */
    private function alias(stdClass $entry): array
    {
        $fields = get_object_vars($entry);

        return array_key_exists('name', $fields)
            && array_key_exists('aliasPath', $fields)
            && is_string($fields['name'])
            && is_string($fields['aliasPath'])
            ? [$fields['name'] => $fields['aliasPath']]
            : [];
    }

    /**
     * @param array<array-key, string> $match A placeholder's match.
     *
     * @return string The asset name it holds.
     */
    private static function named(array $match): string
    {
        return $match[1] ?? '';
    }

    /**
     * @param string                $name    A named asset.
     * @param array<string, string> $aliases The alias paths, by name.
     * @param string                $where   The step and field, for the refusal.
     *
     * @throws InvalidArgumentException When the catalog does not list it.
     *
     * @return string Its alias path.
     */
    private static function aliasOf(string $name, array $aliases, string $where): string
    {
        return array_key_exists($name, $aliases)
            ? $aliases[$name]
            : throw new InvalidArgumentException($where . ' names {asset:' . $name . '}, which ' . self::CATALOG . ' does not list.');
    }
}
