<?php

declare(strict_types=1);

namespace AIC\Http\Domain;

use function array_is_list;
use function array_values;
use function count;
use function is_string;

/**
 * Checked string groups and maps used by the server request's PSR withers.
 * Separates field shape validation from request state and copying.
 */
trait InputFields
{
    /**
     * @param string                   $name    The field name, for the message.
     * @param array<array-key, string> $strings The group's string members.
     * @param int                      $total   How many members the group had.
     *
     * @throws MalformedInputException When a member is not a string, or the group is
     *                                 neither a list nor keyed by strings.
     *
     * @return list<string>|array<string, string> The group as a list or a map.
     */
    private static function fieldGroup(string $name, array $strings, int $total): array
    {
        if (count($strings) !== $total) {
            throw new MalformedInputException('An input field may nest one level of strings at most: ' . $name);
        }

        return self::groupIsList($strings) ? array_values($strings)
            : self::stringMap($name, $strings, $total);
    }

    /**
     * @param string                   $name    What the map holds, for the message.
     * @param array<array-key, string> $strings The string members.
     * @param int                      $total   How many members there were.
     *
     * @throws MalformedInputException When a member is not a string or its key is not a string.
     *
     * @return array<string, string> The members by name.
     */
    private static function stringMap(string $name, array $strings, int $total): array
    {
        $map = [];

        foreach ($strings as $key => $value) {
            if (!is_string($key)) {
                continue;
            }

            $map[$key] = $value;
        }

        if (count($map) !== $total) {
            throw new MalformedInputException('Each ' . $name . ' must be a string under a string name.');
        }

        return $map;
    }

    /**
     * @param array<array-key, string> $strings A checked field group.
     *
     * @return bool Whether its keys form a list.
     */
    private static function groupIsList(array $strings): bool
    {
        return array_is_list($strings);
    }
}
