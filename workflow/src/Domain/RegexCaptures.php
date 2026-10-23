<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain;

use function array_key_exists;
use function preg_match;
use function preg_match_all;

/**
 * Private workflow regex reads with explicit match and capture availability.
 */
trait RegexCaptures
{
    /**
     * @param non-empty-string $pattern A pattern without capture offsets.
     * @param string           $subject The text to read.
     *
     * @return array<array-key, string> The captures of one match, empty when it does not match.
     */
    private static function regexMatch(string $pattern, string $subject): array
    {
        if (preg_match($pattern, $subject, $captures) !== 1) {
            return [];
        }

        return $captures;
    }

    /**
     * @param non-empty-string $pattern A pattern without capture offsets.
     * @param string           $subject The text to read.
     * @param int              $group   The capture column to retain.
     *
     * @return list<string> The requested captures in source order, empty when absent.
     */
    private static function regexColumn(string $pattern, string $subject, int $group): array
    {
        $count = preg_match_all($pattern, $subject, $columns);

        return $count !== false && array_key_exists($group, $columns)
            ? $columns[$group]
            : [];
    }
}
