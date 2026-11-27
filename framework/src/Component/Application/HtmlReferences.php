<?php

declare(strict_types=1);

namespace AIC\Component\Application;

use function in_array;
use function str_starts_with;
use function strlen;
use function strpos;
use function strtolower;
use function substr;

/**
 * The character references the sanitizer keeps as written (#337): a numeric
 * one for a character a browser reads without a parse error (no NUL, no
 * control but tab, line feed and form feed, no surrogate, no noncharacter,
 * nothing past U+10FFFF) and a named one from a short list. Any other `&`
 * is escaped, so the reader sees what was typed.
 */
final readonly class HtmlReferences
{
    /** The names kept: XML's five and the common typographic ones. */
    private const array NAMED = [
        'amp', 'apos', 'bull', 'cent', 'copy', 'deg', 'divide', 'euro', 'gt', 'hellip', 'laquo', 'ldquo', 'lsquo',
        'lt', 'mdash', 'middot', 'nbsp', 'ndash', 'para', 'pound', 'quot', 'raquo', 'rdquo', 'reg', 'rsquo', 'sect',
        'times', 'trade', 'yen',
    ];

    /** The digits, by value. */
    private const string DIGITS = '0123456789abcdef';

    /**
     * @param string $text   The input.
     * @param int    $offset Where an `&` is.
     *
     * @return int The length of the reference kept there, `;` included; 0 when none is.
     */
    public function length(string $text, int $offset): int
    {
        $end = strpos($text, ';', $offset);

        if ($end === false || $end - $offset > 12) {
            return 0;
        }

        return $this->kept(substr($text, $offset + 1, $end - $offset - 1))
            ? $end + 1 - $offset
            : 0;
    }

    /**
     * @param string $body A reference between its `&` and its `;`.
     *
     * @return bool Whether it is kept.
     */
    private function kept(string $body): bool
    {
        if (str_starts_with(strtolower($body), '#x')) {
            return $this->readable($this->number(strtolower(substr($body, 2)), 16));
        }

        if (str_starts_with($body, '#')) {
            return $this->readable($this->number(substr($body, 1), 10));
        }

        return in_array($body, self::NAMED, true);
    }

    /**
     * @param string $digits Digits, lower case.
     * @param int    $base   10 or 16.
     *
     * @return int Their value; -1 when there are none, more than seven, or one is not of the base.
     */
    private function number(string $digits, int $base): int
    {
        $length = strlen($digits);

        if ($length === 0 || $length > 7) {
            return -1;
        }

        $value = 0;

        for ($offset = 0; $offset < $length; $offset += 1) {
            $digit = $this->digit(substr($digits, $offset, 1), $base);

            if ($digit < 0) {
                return -1;
            }

            $value = $value * $base + $digit;
        }

        return $value;
    }

    /**
     * @param string $char One character, lower case.
     * @param int    $base 10 or 16.
     *
     * @return int Its value as a digit of the base; -1 when it is none.
     */
    private function digit(string $char, int $base): int
    {
        $digit = strpos(self::DIGITS, $char);

        return $digit === false || $digit >= $base
            ? -1
            : $digit;
    }

    /**
     * @param int $code A code point; -1 for none.
     *
     * @return bool Whether a browser reads its reference without a parse error.
     */
    private function readable(int $code): bool
    {
        return match (true) {
            $code === 9, $code === 10, $code === 12 => true,
            $code < 32, $code < 160 && $code >= 127 => false,
            default => $code <= 0x10FFFF && $this->character($code),
        };
    }

    /**
     * @param int $code A code point from U+00A0 to U+10FFFF, or a printable ASCII one.
     *
     * @return bool Whether it is neither a surrogate nor a noncharacter.
     */
    private function character(int $code): bool
    {
        $surrogate = $code >= 0xD800 && $code <= 0xDFFF;
        $noncharacter = ($code >= 0xFDD0 && $code <= 0xFDEF) || ($code & 0xFFFE) === 0xFFFE;

        return !$surrogate && !$noncharacter;
    }
}
