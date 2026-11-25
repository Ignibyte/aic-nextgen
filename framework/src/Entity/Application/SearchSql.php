<?php

declare(strict_types=1);

namespace AIC\Entity\Application;

use InvalidArgumentException;

use function array_fill;
use function implode;
use function mb_trim;
use function strtr;

/**
 * A search's condition (#184, #329): any of several columns contains a text,
 * case-insensitively, as typed (`%` and `_` are literal).
 */
final readonly class SearchSql
{
    /**
     * @param list<string> $columns The columns, each safe to write into SQL; at least one.
     * @param string       $text    What to find.
     *
     * @throws InvalidArgumentException When no column is given.
     *
     * @return string The condition, one `?` per column; empty for a blank text.
     */
    public function clause(array $columns, string $text): string
    {
        if ($columns === []) {
            throw new InvalidArgumentException('A search names at least one field.');
        }

        return mb_trim($text) === ''
            ? ''
            : '(' . implode(' ILIKE ? OR ', $columns) . ' ILIKE ?)';
    }

    /**
     * @param int    $count How many columns it searches.
     * @param string $text  What to find.
     *
     * @return list<string> Its values, one per column.
     */
    public function params(int $count, string $text): array
    {
        $pattern = '%' . strtr(mb_trim($text), ['\\' => '\\\\', '%' => '\\%', '_' => '\\_']) . '%';

        return array_fill(0, $count, $pattern);
    }
}
