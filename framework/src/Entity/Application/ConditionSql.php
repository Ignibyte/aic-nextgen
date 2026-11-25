<?php

declare(strict_types=1);

namespace AIC\Entity\Application;

use AIC\Entity\Domain\Comparison;
use InvalidArgumentException;

use function is_bool;

/**
 * How EntityConditions writes one condition (#184, #311): the comparison's
 * SQL, a value as bound, and a test against null.
 */
final readonly class ConditionSql
{
    /**
     * @param string     $column     The column, safe to write into SQL.
     * @param Comparison $comparison How it is compared.
     *
     * @return string The condition, with one `?` for its value: the operator, or for #311's a cast to jsonb or a lower
     *                case.
     */
    public function compared(string $column, Comparison $comparison): string
    {
        if ($comparison === Comparison::Contains) {
            return $column . ' @> ?::jsonb';
        }

        if ($comparison === Comparison::EqualsIgnoringCase) {
            return 'lower(' . $column . ') = lower(?)';
        }

        return $column . ' ' . $comparison->value . ' ?';
    }

    /**
     * @param string|int|bool $value A condition's value.
     *
     * @return string|int It as bound: a bool as `true` or `false`.
     */
    public function bound(string|int|bool $value): string|int
    {
        if (!is_bool($value)) {
            return $value;
        }

        return $value
            ? 'true'
            : 'false';
    }

    /**
     * @param Comparison $comparison How a field is compared with null.
     *
     * @throws InvalidArgumentException When it is an ordering comparison.
     *
     * @return string ` IS NULL` or ` IS NOT NULL`.
     */
    public function nullTest(Comparison $comparison): string
    {
        if ($comparison === Comparison::Equals) {
            return ' IS NULL';
        }

        if ($comparison === Comparison::NotEquals) {
            return ' IS NOT NULL';
        }

        throw new InvalidArgumentException('Only Equals and NotEquals compare a field with null.');
    }
}
