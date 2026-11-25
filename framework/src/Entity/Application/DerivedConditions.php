<?php

declare(strict_types=1);

namespace AIC\Entity\Application;

use AIC\Entity\Domain\Comparison;
use AIC\Entity\Domain\Condition;
use InvalidArgumentException;

use function count;
use function implode;
use function in_array;

/**
 * A derived query's conditions (#329): each over a column of its rows, joined
 * by AND, every value bound.
 */
final class DerivedConditions
{
    /**
     * The conditions, each complete.
     *
     * @var list<string>
     */
    private array $clauses = [];

    /**
     * Their values, in order.
     *
     * @var list<string|int>
     */
    private array $params = [];

    /**
     * @param DerivedColumns $derivedColumns The rows' columns.
     */
    public function __construct(
        private readonly DerivedColumns $derivedColumns,
    ) {
    }

    /**
     * @param Condition $condition A column compared with a value; null asks whether it is null.
     *
     * @throws InvalidArgumentException When the rows have no such column, null meets an ordering comparison, or the
     *                                  comparison is `Contains` or `Similar`, which compare a field in its own query.
     */
    public function add(Condition $condition): void
    {
        $column = $this->column($condition->field);
        $comparison = $condition->comparison;
        $value = $condition->value;
        $conditionSql = new ConditionSql();

        if ($comparison === Comparison::Contains || $comparison === Comparison::Similar) {
            throw new InvalidArgumentException('A derived query compares a column by value, not by ' . $comparison->name . '.');
        }

        if ($value === null) {
            $this->clauses[] = $column . $conditionSql->nullTest($comparison);

            return;
        }

        $this->clauses[] = $conditionSql->compared($column, $comparison);
        $this->params[] = $conditionSql->bound($value);
    }

    /**
     * @param list<string> $columns Columns of the rows, any of which may contain the text.
     * @param string       $text    What to find, matched case-insensitively; blank adds no condition.
     *
     * @throws InvalidArgumentException When no column is given, or the rows lack one.
     */
    public function search(array $columns, string $text): void
    {
        $searched = [];

        foreach ($columns as $column) {
            $searched[] = $this->column($column) . '::text';
        }

        $searchSql = new SearchSql();
        $clause = $searchSql->clause($searched, $text);

        if ($clause === '') {
            return;
        }

        $this->clauses[] = $clause;
        $this->params = [...$this->params, ...$searchSql->params(count($searched), $text)];
    }

    /**
     * @param string $name A column of the rows.
     *
     * @throws InvalidArgumentException When they have no such column.
     *
     * @return string It, safe to write into SQL.
     */
    public function column(string $name): string
    {
        if (!in_array($name, $this->derivedColumns->names(), true)) {
            throw new InvalidArgumentException('The query reads no column "' . $name . '".');
        }

        return 'aic_rows.' . $name;
    }

    /**
     * @return string ` WHERE …` with every condition; empty when there is none.
     */
    public function sql(): string
    {
        return $this->clauses === []
            ? ''
            : ' WHERE ' . implode(' AND ', $this->clauses);
    }

    /**
     * @return list<string|int> The conditions' values, in the order of their marks.
     */
    public function params(): array
    {
        return $this->params;
    }
}
