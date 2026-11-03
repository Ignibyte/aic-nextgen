<?php

declare(strict_types=1);

namespace Gates\Domain;

use function array_all;
use function count;
use function in_array;
use function preg_split;

use const PREG_SPLIT_DELIM_CAPTURE;

/**
 * Where gate:23 lets two answers differ by their sites' record ids (#217): at
 * a number after a `-` or a `=` (an element id, a key, a query), the two may
 * differ only when the PHP site's number is one of its own ids and the
 * compiled site's one of its. Every other byte must match. So a small id
 * cannot mask unrelated text on one side only (`utf-8`, `initial-scale=1`,
 * `>=500`), which made every page of a run differ.
 */
final readonly class CapturedIds
{
    /** A number after a `-` or a `=`, not followed by a word character or a `-`. */
    private const string NUMBER = '#(?<=[-=])(\\d+)(?![\\w-])#';

    /**
     * @param list<string> $php    The PHP site's record ids.
     * @param list<string> $native The compiled site's record ids.
     */
    public function __construct(
        private array $php,
        private array $native,
    ) {
    }

    /**
     * @param string $php    Text from the PHP site's answer, normalised.
     * @param string $native The same text from the compiled site's answer, normalised.
     *
     * @return bool Whether they match, each number apart only where each side has its own record id.
     */
    public function agree(string $php, string $native): bool
    {
        if ($php === $native) {
            return true;
        }

        $phpParts = self::parts($php);
        $nativeParts = self::parts($native);

        if (count($phpParts) !== count($nativeParts)) {
            return false;
        }

        return array_all($phpParts, fn (string $phpPart, int $index): bool => $this->allowed($index, $phpPart, $nativeParts[$index] ?? ''));
    }

    /**
     * @param int    $index  The part's position: even for text, odd for a number.
     * @param string $php    The PHP site's part.
     * @param string $native The compiled site's part.
     *
     * @return bool Whether the two parts may stand side by side.
     */
    private function allowed(int $index, string $php, string $native): bool
    {
        if ($php === $native) {
            return true;
        }

        return $index % 2 === 1 && in_array($php, $this->php, true) && in_array($native, $this->native, true);
    }

    /**
     * @param string $text Normalised answer text.
     *
     * @return list<string> Its text and its numbers after a `-` or a `=`, alternating, text first.
     */
    private static function parts(string $text): array
    {
        $parts = preg_split(self::NUMBER, $text, -1, PREG_SPLIT_DELIM_CAPTURE);

        return $parts === false
            ? [$text]
            : $parts;
    }
}
