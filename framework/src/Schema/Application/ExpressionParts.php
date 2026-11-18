<?php

declare(strict_types=1);

namespace AIC\Schema\Application;

use InvalidArgumentException;

use function array_unique;
use function array_values;
use function count;
use function implode;

/**
 * How PostgresExpressions joins the parts of a compound term or predicate
 * (#310): their SQL in parentheses, and the columns they read, once each.
 */
final readonly class ExpressionParts
{
    /**
     * @param list<string> $sql  Two or more rendered parts.
     * @param string       $glue What joins them, such as ` AND `.
     *
     * @throws InvalidArgumentException When there are fewer than two.
     *
     * @return string The parts joined, in parentheses.
     */
    public function joined(array $sql, string $glue): string
    {
        if (count($sql) < 2) {
            throw new InvalidArgumentException('A concatenation, all() or any() takes two or more parts.');
        }

        return '(' . implode($glue, $sql) . ')';
    }

    /**
     * @param list<list<string>> $columns The columns each part reads.
     *
     * @return list<string> Every column, once, in order of first use.
     */
    public function columns(array $columns): array
    {
        $read = [];

        foreach ($columns as $list) {
            foreach ($list as $column) {
                $read[] = $column;
            }
        }

        return array_values(array_unique($read));
    }
}
