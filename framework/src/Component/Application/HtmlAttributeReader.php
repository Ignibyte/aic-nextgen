<?php

declare(strict_types=1);

namespace AIC\Component\Application;

use AIC\Component\Domain\HtmlAttribute;
use AIC\Component\Domain\HtmlAttributes;

use function array_key_exists;
use function ctype_alnum;
use function str_contains;
use function strlen;
use function strpos;
use function strtolower;
use function substr;

/**
 * Reads a tag's name and its attributes for `HtmlTokenizer` (#337): a name of
 * letters, digits and `-`, and attributes with a value in double quotes, in
 * single quotes, unquoted or none, as the HTML standard reads them.
 */
final readonly class HtmlAttributeReader
{
    /** The space characters between attributes. */
    private const string SPACE = " \t\n\r\f";

    /**
     * @param string $html   The input.
     * @param int    $offset Where the name starts.
     *
     * @return string The name as the input has it; empty when none starts there.
     */
    public function name(string $html, int $offset): string
    {
        $end = $offset;
        $length = strlen($html);

        while ($end < $length && (ctype_alnum(substr($html, $end, 1)) || substr($html, $end, 1) === '-')) {
            $end += 1;
        }

        return substr($html, $offset, $end - $offset);
    }

    /**
     * @param string $html   The input.
     * @param int    $offset Just past the tag's name.
     *
     * @return HtmlAttributes The attributes, and just past the `>`; -1 when the input ends inside the tag.
     */
    public function attributes(string $html, int $offset): HtmlAttributes
    {
        $values = [];
        $length = strlen($html);
        $offset = $this->skip($html, $offset, self::SPACE . '/');

        while ($offset < $length && substr($html, $offset, 1) !== '>') {
            $htmlAttribute = $this->attribute($html, $offset);

            if (!array_key_exists($htmlAttribute->name, $values)) {
                $values[$htmlAttribute->name] = $htmlAttribute->value;
            }

            $offset = $this->skip($html, $htmlAttribute->next, self::SPACE . '/');
        }

        return new HtmlAttributes($values, $offset < $length ? $offset + 1 : -1);
    }

    /**
     * @param string $html   The input.
     * @param int    $offset Where an attribute starts.
     *
     * @return HtmlAttribute The attribute.
     */
    private function attribute(string $html, int $offset): HtmlAttribute
    {
        $end = $this->until($html, $offset, self::SPACE . '/>=');
        $name = strtolower(substr($html, $offset, $end - $offset));
        $next = $this->skip($html, $end, self::SPACE);

        if (substr($html, $next, 1) !== '=') {
            return new HtmlAttribute($name, '', $next);
        }

        return $this->value($html, $name, $this->skip($html, $next + 1, self::SPACE));
    }

    /**
     * @param string $html   The input.
     * @param string $name   The attribute's name.
     * @param int    $offset Where its value starts.
     *
     * @return HtmlAttribute The attribute with its value, quoted or not.
     */
    private function value(string $html, string $name, int $offset): HtmlAttribute
    {
        $quote = substr($html, $offset, 1);

        if ($quote !== '"' && $quote !== "'") {
            $end = $this->until($html, $offset, self::SPACE . '>');

            return new HtmlAttribute($name, substr($html, $offset, $end - $offset), $end);
        }

        $close = strpos($html, $quote, $offset + 1);

        return $close === false
            ? new HtmlAttribute($name, substr($html, $offset + 1), strlen($html))
            : new HtmlAttribute($name, substr($html, $offset + 1, $close - $offset - 1), $close + 1);
    }

    /**
     * @param string $html   The input.
     * @param int    $offset Where to start.
     * @param string $chars  The characters to pass over.
     *
     * @return int The first place at or after `$offset` that holds none of them.
     */
    private function skip(string $html, int $offset, string $chars): int
    {
        $length = strlen($html);

        while ($offset < $length && str_contains($chars, substr($html, $offset, 1))) {
            $offset += 1;
        }

        return $offset;
    }

    /**
     * @param string $html   The input.
     * @param int    $offset Where to start.
     * @param string $chars  The characters to stop at.
     *
     * @return int The first place at or after `$offset` that holds one of them; the input's end when none does.
     */
    private function until(string $html, int $offset, string $chars): int
    {
        $length = strlen($html);

        while ($offset < $length && !str_contains($chars, substr($html, $offset, 1))) {
            $offset += 1;
        }

        return $offset;
    }
}
