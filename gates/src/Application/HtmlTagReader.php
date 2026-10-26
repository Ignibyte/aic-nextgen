<?php

declare(strict_types=1);

namespace Gates\Application;

use Gates\Domain\HtmlAttribute;
use Gates\Domain\HtmlTag;

use function preg_match;
use function strlen;
use function strtolower;
use function substr_count;

use const PREG_UNMATCHED_AS_NULL;

/**
 * Reads one start tag the way the HTML tokenizer splits it (#170): the name,
 * then each attribute with the whitespace before it, its `=` and its value
 * with the quote it was written in, then what stands before `>`.
 */
final readonly class HtmlTagReader
{
    /** The `<` and the element name. */
    private const string NAME = '/\\G<([a-z][^\\s\\/>]*)/i';

    /** One attribute: the gap, the name, and an optional `=` with a double-quoted, single-quoted or bare value. */
    private const string ATTRIBUTE = '/\\G(\\s*)([^\\s\\/>=]+)(?:(\\s*)=(\\s*)(?:"([^"]*)"|\'([^\']*)\'|([^\\s>]*)))?/';

    /** The tag's end: whitespace and a self-closing slash, then `>`. */
    private const string END = '/\\G([\\s\\/]*)>/';

    /**
     * @param string $html The markup.
     * @param int    $open The offset of a `<`.
     * @param int    $line The line that `<` is on.
     *
     * @return array{HtmlTag, int}|null The tag, its body left empty, and the offset after its `>` (the end for
     *                                  a tag left open); null when no element name follows the `<`.
     */
    public function read(string $html, int $open, int $line): ?array
    {
        $name = self::match(self::NAME, $html, $open);
        $token = $name[0] ?? null;
        $element = $name[1] ?? null;

        if ($token === null || $element === null) {
            return null;
        }

        return $this->attributes($html, [$open, $line], strtolower($element), $open + strlen($token));
    }

    /**
     * @param string          $html   The markup.
     * @param array{int, int} $start  The tag's `<` offset and line.
     * @param string          $name   The element name, lower case.
     * @param int             $offset The offset after the name.
     *
     * @return array{HtmlTag, int} The tag and the offset after its `>`, or the end of the markup.
     */
    private function attributes(string $html, array $start, string $name, int $offset): array
    {
        $attributes = [];

        while ($offset < strlen($html)) {
            $end = self::match(self::END, $html, $offset);
            $token = $end[0] ?? null;

            if ($token !== null) {
                return [new HtmlTag($name, $attributes, $start[1], $end[1] ?? '', ''), $offset + strlen($token)];
            }

            [$attribute, $offset] = $this->next($html, $start, $offset);

            if (!($attribute instanceof HtmlAttribute)) {
                continue;
            }

            $attributes[] = $attribute;
        }

        return [new HtmlTag($name, $attributes, $start[1], '', ''), strlen($html)];
    }

    /**
     * @param string          $html   The markup.
     * @param array{int, int} $start  The tag's `<` offset and line.
     * @param int             $offset Where the next attribute may start.
     *
     * @return array{HtmlAttribute|null, int} The attribute there and the offset after it; a character no
     *                                        attribute starts with, such as a stray `/`, is passed over.
     */
    private function next(string $html, array $start, int $offset): array
    {
        $row = self::match(self::ATTRIBUTE, $html, $offset);
        $token = $row[0] ?? null;

        if ($token === null) {
            return [null, $offset + 1];
        }

        $line = $start[1] + substr_count($html, "\n", $start[0], $offset - $start[0] + strlen($row[1] ?? ''));

        return [$this->attribute($row, $line), $offset + strlen($token)];
    }

    /**
     * @param array<array-key, string|null> $row  One attribute match.
     * @param int                           $line The line its name starts on.
     *
     * @return HtmlAttribute The attribute as written.
     */
    private function attribute(array $row, int $line): HtmlAttribute
    {
        $quote = match (true) {
            ($row[5] ?? null) !== null => '"',
            ($row[6] ?? null) !== null => "'",
            default => '',
        };
        $spaced = ($row[3] ?? '') !== '' || ($row[4] ?? '') !== '';

        return new HtmlAttribute(strtolower($row[2] ?? ''), $row[5] ?? $row[6] ?? $row[7] ?? null, $quote, $row[1] ?? '', $spaced, $line);
    }

    /**
     * @param non-empty-string $pattern An anchored pattern.
     * @param string           $html    The markup.
     * @param int              $offset  Where the match must start.
     *
     * @return array<array-key, string|null> The match and its captures, unmatched ones null; empty on no match.
     */
    private static function match(string $pattern, string $html, int $offset): array
    {
        preg_match($pattern, $html, $matches, PREG_UNMATCHED_AS_NULL, $offset);

        return $matches;
    }
}
