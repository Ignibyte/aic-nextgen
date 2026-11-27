<?php

declare(strict_types=1);

namespace AIC\Component\Application;

use function in_array;
use function ord;
use function str_contains;
use function strlen;
use function strpos;
use function strtolower;
use function substr;
use function trim;

/**
 * Writes the sanitizer's text and attribute values again (#337): `<` and `>`
 * always escaped, `"` too in a value, and `&` kept only where a reference
 * `HtmlReferences` keeps starts, so such a reference reads as it did and any
 * other `&` becomes `&amp;`. It also judges a link.
 */
final readonly class HtmlEscaper
{
    /** The schemes a link may name. */
    private const array SCHEMES = ['http', 'https', 'mailto'];

    /** What a browser strips round a URL, and refuses before its scheme ends. */
    private const string CONTROL = " \t\n\r\f\v\0";

    /**
     * @param HtmlReferences $htmlReferences The references kept as written.
     */
    public function __construct(
        private HtmlReferences $htmlReferences = new HtmlReferences(),
    ) {
    }

    /**
     * @param string $text Text as the input had it.
     *
     * @return string The text, escaped.
     */
    public function text(string $text): string
    {
        return $this->escape($text, false);
    }

    /**
     * @param string $value A value as the input had it.
     *
     * @return string The value, escaped for double quotes.
     */
    public function attribute(string $value): string
    {
        return $this->escape($value, true);
    }

    /**
     * @param string $href A link as the input had it.
     *
     * @return string The link trimmed, when it is relative or names `http`, `https` or `mailto`; empty otherwise.
     */
    public function href(string $href): string
    {
        $link = trim($href, self::CONTROL);
        $prefix = substr($link, 0, $this->firstOf($link, '/?#'));
        $colon = strpos($prefix, ':');

        if ($colon === false) {
            return $this->plain($prefix)
                ? $link
                : '';
        }

        return in_array(strtolower(substr($prefix, 0, $colon)), self::SCHEMES, true)
            ? $link
            : '';
    }

    /**
     * @param string $text   The input.
     * @param bool   $quoted Whether `"` is escaped too.
     *
     * @return string It escaped.
     */
    private function escape(string $text, bool $quoted): string
    {
        $escaped = '';
        $length = strlen($text);
        $offset = 0;

        while ($offset < $length) {
            $char = substr($text, $offset, 1);
            $reference = $char === '&'
                ? $this->htmlReferences->length($text, $offset)
                : 0;

            if ($reference > 0) {
                $escaped .= substr($text, $offset, $reference);
                $offset += $reference;

                continue;
            }

            $escaped .= $this->character($char, $quoted);
            $offset += 1;
        }

        return $escaped;
    }

    /**
     * @param string $char   One character that starts no reference.
     * @param bool   $quoted Whether `"` is escaped too.
     *
     * @return string It escaped.
     */
    private function character(string $char, bool $quoted): string
    {
        return match (true) {
            $char === '&' => '&amp;',
            $char === '<' => '&lt;',
            $char === '>' => '&gt;',
            $char === '"' && $quoted => '&quot;',
            default => $char,
        };
    }

    /**
     * @param string $link  A link.
     * @param string $chars What ends its first part.
     *
     * @return int Where the first of them is; the link's length when none is there.
     */
    private function firstOf(string $link, string $chars): int
    {
        $offset = 0;

        while ($offset < strlen($link) && !str_contains($chars, substr($link, $offset, 1))) {
            $offset += 1;
        }

        return $offset;
    }

    /**
     * @param string $prefix A relative link up to its first `/`, `?` or `#`.
     *
     * @return bool Whether it holds no reference and no control character, which could hide a scheme's `:`.
     */
    private function plain(string $prefix): bool
    {
        for ($offset = 0; $offset < strlen($prefix); $offset += 1) {
            $char = substr($prefix, $offset, 1);

            if ($char === '&' || ord($char[0]) < 32 || ord($char[0]) === 127) {
                return false;
            }
        }

        return true;
    }
}
