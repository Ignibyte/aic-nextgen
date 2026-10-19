<?php

declare(strict_types=1);

namespace AIC\Http\Domain;

use function array_key_exists;
use function explode;
use function str_contains;
use function str_ends_with;
use function strlen;
use function strpos;
use function substr;
use function trim;
use function urldecode;

/**
 * Parses request input from its encoded strings into AIC's typed shapes.
 *
 * A field is a string (`name=v`), a list of strings (`name[]=a&name[]=b`) or a
 * map of strings (`name[key]=v`). Deeper nesting, such as `name[a][b]=v`, is
 * refused: code downstream reads typed values and never `mixed`. Duplicate plain
 * names keep the last value, as PHP does.
 */
final readonly class InputParser
{
    /**
     * Parse a query string or an `application/x-www-form-urlencoded` body.
     *
     * @param string $encoded The encoded fields, `&`-separated.
     *
     * @throws MalformedInputException When a field nests deeper than one level or
     *                                 one name is used in two shapes.
     *
     * @return array<string, string|list<string>|array<string, string>> The fields by name.
     */
    public function fields(string $encoded): array
    {
        $strings = [];
        $fieldGroups = new FieldGroups();

        foreach (explode('&', $encoded) as $pair) {
            $name = self::decodedName($pair);
            $open = self::bracketAt($name);

            if ($open === strlen($name)) {
                $strings = self::withString($strings, $name, self::decodedValue($pair));
                continue;
            }

            $fieldGroups->add(self::base($name, $open), self::key($name, $open), self::decodedValue($pair));
        }

        return self::merged($strings, $fieldGroups);
    }

    /**
     * Parse a `Cookie` request header. A name that appears twice keeps its first
     * value, as PHP does.
     *
     * @param string $header The header line, `;`-separated `name=value` pairs.
     *
     * @return array<string, string> The cookie values by name.
     */
    public function cookies(string $header): array
    {
        $cookies = [];

        foreach (explode(';', $header) as $pair) {
            $name = self::cookieName($pair);

            if ($name === '' || array_key_exists($name, $cookies)) {
                continue;
            }

            $cookies[$name] = self::cookieValue($pair);
        }

        return $cookies;
    }

    /**
     * @param array<string, string> $strings The plain fields so far.
     * @param string                $name    A plain field's name; an empty name is skipped.
     * @param string                $value   Its decoded value.
     *
     * @return array<string, string> The plain fields with this one set.
     */
    private static function withString(array $strings, string $name, string $value): array
    {
        if ($name !== '') {
            $strings[$name] = $value;
        }

        return $strings;
    }

    /**
     * @param string $name A decoded field name.
     *
     * @return int The position of its first `[`; its length when it has none.
     */
    private static function bracketAt(string $name): int
    {
        $length = strlen($name);

        for ($position = 0; $position < $length; $position += 1) {
            if ($name[$position] === '[') {
                return $position;
            }
        }

        return $length;
    }

    /**
     * @param string $pair One cookie pair, `name=value`.
     *
     * @return string The cookie name, trimmed.
     */
    private static function cookieName(string $pair): string
    {
        $equals = strpos($pair, '=');

        return trim($equals === false ? $pair : substr($pair, 0, $equals));
    }

    /**
     * @param string $pair One cookie pair, `name=value`.
     *
     * @return string The decoded value; empty when the pair has no `=`.
     */
    private static function cookieValue(string $pair): string
    {
        $equals = strpos($pair, '=');

        return $equals === false
            ? ''
            : urldecode(trim(substr($pair, $equals + 1)));
    }

    /**
     * @param string $pair One `name=value` pair.
     *
     * @return string The decoded name.
     */
    private static function decodedName(string $pair): string
    {
        $equals = strpos($pair, '=');

        return urldecode($equals === false ? $pair : substr($pair, 0, $equals));
    }

    /**
     * @param string $pair One `name=value` pair.
     *
     * @return string The decoded value; empty when the pair has no `=`.
     */
    private static function decodedValue(string $pair): string
    {
        $equals = strpos($pair, '=');

        return $equals === false
            ? ''
            : urldecode(substr($pair, $equals + 1));
    }

    /**
     * @param string $name The decoded name.
     * @param int    $open The position of its first `[`.
     *
     * @throws MalformedInputException When nothing precedes the bracket.
     *
     * @return string The name before the bracket.
     */
    private static function base(string $name, int $open): string
    {
        $base = substr($name, 0, $open);

        if ($base === '') {
            throw new MalformedInputException('An input field name must not start with a bracket: ' . $name);
        }

        return $base;
    }

    /**
     * @param string $name The decoded name.
     * @param int    $open The position of its first `[`.
     *
     * @throws MalformedInputException When the name nests deeper than one level.
     *
     * @return string The key between the brackets; empty for a list entry.
     */
    private static function key(string $name, int $open): string
    {
        $key = substr($name, $open + 1, strlen($name) - $open - 2);

        if (!str_ends_with($name, ']') || str_contains($key, '[') || str_contains($key, ']')) {
            throw new MalformedInputException('An input field may nest one level at most: ' . $name);
        }

        return $key;
    }

    /**
     * @param array<string, string> $strings     The plain fields.
     * @param FieldGroups           $fieldGroups The list and map fields.
     *
     * @throws MalformedInputException When one name is used in two shapes.
     *
     * @return array<string, string|list<string>|array<string, string>> All fields by name.
     */
    private static function merged(array $strings, FieldGroups $fieldGroups): array
    {
        $fields = $strings;

        foreach ($fieldGroups->lists() as $name => $list) {
            self::assertUnused($fields, $name);
            $fields[$name] = $list;
        }

        foreach ($fieldGroups->maps() as $name => $map) {
            self::assertUnused($fields, $name);
            $fields[$name] = $map;
        }

        return $fields;
    }

    /**
     * @param array<string, string|list<string>|array<string, string>> $fields The fields so far.
     * @param string                                                   $name   The name to add.
     *
     * @throws MalformedInputException When the name is already used.
     */
    private static function assertUnused(array $fields, string $name): void
    {
        if (array_key_exists($name, $fields)) {
            throw new MalformedInputException('An input field name is used in two shapes: ' . $name);
        }
    }
}
