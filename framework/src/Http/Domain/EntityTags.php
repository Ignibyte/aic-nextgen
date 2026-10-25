<?php

declare(strict_types=1);

namespace AIC\Http\Domain;

use function in_array;
use function preg_match;
use function strlen;
use function trim;

/** Compare complete HTTP entity-tag fields without splitting commas inside opaque tags. */
final readonly class EntityTags
{
    /** RFC entity tags have no quoted-string escaping; commas and backslashes can be opaque bytes. */
    private const string TAG = '(?:W/)?"[\\x21\\x23-\\x7E\\x80-\\xFF]*"';

    /**
     * @param string $field   The full conditional header value.
     * @param string $current The current strong quoted entity tag.
     * @param bool   $weak    Whether weak comparison is permitted.
     *
     * @return bool Whether a valid wildcard or list contains a matching entity tag.
     */
    public function matches(string $field, string $current, bool $weak): bool
    {
        $field = trim($field, " \t");

        if ($field === '*') {
            return true;
        }

        $list = '#^[ \\t]*(?:' . self::TAG . '[ \\t]*)?(?:,[ \\t]*(?:' . self::TAG . '[ \\t]*)?)*\\z#';

        if (preg_match($list, $field) !== 1) {
            return false;
        }

        $prefix = '#^[ \\t]*(?:(?:' . self::TAG . ')?[ \\t]*,[ \\t]*)*';
        $strength = $weak
            ? '(?:W/)?'
            : '';

        return preg_match($prefix . $strength . $this->quoted($current) . '[ \\t]*(?:,|\\z)#', $field) === 1;
    }

    /**
     * @param string $tag The current complete quoted strong tag.
     *
     * @return string Literal regex bytes, preserving opaque commas and backslashes without enabling pattern syntax.
     */
    private function quoted(string $tag): string
    {
        $escaped = '';
        $metacharacters = ['\\', '.', '^', '$', '|', '?', '*', '+', '(', ')', '[', ']', '{', '}', '#'];

        for ($index = 0; $index < strlen($tag); $index += 1) {
            $byte = $tag[$index];
            $escaped .= in_array($byte, $metacharacters, true)
                ? '\\' . $byte
                : $byte;
        }

        return $escaped;
    }
}
