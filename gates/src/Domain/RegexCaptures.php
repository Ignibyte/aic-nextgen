<?php

declare(strict_types=1);

namespace Gates\Domain;

use function array_key_exists;
use function preg_match;
use function preg_match_all;

use const PREG_SET_ORDER;

/**
 * Regex result rows whose captures callers can check independently of pattern inference.
 */
trait RegexCaptures
{
    /**
     * @param non-empty-string $pattern A pattern with string captures.
     * @param string           $subject The input.
     *
     * @return array<array-key, string> The first match and captures; empty on no match.
     */
    private static function regexMatch(string $pattern, string $subject): array
    {
        preg_match($pattern, $subject, $matches);

        return $matches;
    }

    /**
     * @param non-empty-string $pattern A pattern with string captures.
     * @param string           $subject The input.
     *
     * @return list<array<array-key, string>> One capture row per match.
     */
    private static function regexRows(string $pattern, string $subject): array
    {
        preg_match_all($pattern, $subject, $matches, PREG_SET_ORDER);

        return $matches;
    }

    /**
     * @param non-empty-string $pattern A pattern with string captures.
     * @param string           $subject The input.
     * @param int              $group   The numbered capture to collect.
     *
     * @return list<string> That capture from each matching row that contains it.
     */
    private static function regexColumn(string $pattern, string $subject, int $group): array
    {
        $column = [];

        foreach (self::regexRows($pattern, $subject) as $row) {
            if (!array_key_exists($group, $row)) {
                continue;
            }

            $column[] = $row[$group];
        }

        return $column;
    }
}
