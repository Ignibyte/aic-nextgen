<?php

declare(strict_types=1);

namespace AIC\Entity\Application;

use AIC\Database\Contracts\Database;
use AIC\Database\Contracts\QueryException;
use InvalidArgumentException;

/**
 * What a query's matching rows add up to (#184): their count, a field's sum
 * and largest value, and a field's distinct values. Sums and maxima come back
 * as text, so a Numeric field's money never passes through a float.
 */
final readonly class EntityTotals
{
    /**
     * @param Database         $database         The database.
     * @param EntityJoins      $entityJoins      The query's table and joins.
     * @param EntityConditions $entityConditions The query's conditions.
     */
    public function __construct(
        private Database $database,
        private EntityJoins $entityJoins,
        private EntityConditions $entityConditions,
    ) {
    }

    /**
     * @throws QueryException When the query fails.
     *
     * @return int How many rows match.
     */
    public function count(): int
    {
        return $this->database->fetchOne($this->select('count(*)::integer'), $this->entityConditions->params())->int('total');
    }

    /**
     * @param string $field An Integer or Numeric field.
     *
     * @throws QueryException           When the query fails.
     * @throws InvalidArgumentException When the entity type has no such field.
     *
     * @return string The matching rows' sum, as text; `0` when none match.
     */
    public function sum(string $field): string
    {
        $sql = $this->select('coalesce(sum(' . $this->entityConditions->column($field) . '), 0)::text');

        return $this->database->fetchOne($sql, $this->entityConditions->params())->string('total');
    }

    /**
     * @param string $field A field.
     *
     * @throws QueryException           When the query fails.
     * @throws InvalidArgumentException When the entity type has no such field.
     *
     * @return string|null The matching rows' largest value, as text; null when none match.
     */
    public function max(string $field): ?string
    {
        $sql = $this->select('max(' . $this->entityConditions->column($field) . ')::text');

        return $this->database->fetchOne($sql, $this->entityConditions->params())->stringOrNull('total');
    }

    /**
     * @param string $field A field.
     *
     * @throws QueryException           When the query fails.
     * @throws InvalidArgumentException When the entity type has no such field.
     *
     * @return list<string> The field's distinct values among the matching rows, as text, in the field's order; null
     *                      values left out.
     */
    public function values(string $field): array
    {
        $column = $this->entityConditions->column($field);
        $sql = 'SELECT DISTINCT ' . $column . ', ' . $column . '::text AS value FROM ' . $this->entityJoins->from()
            . $this->entityConditions->sql() . ($this->entityConditions->hasAny() ? ' AND ' : ' WHERE ')
            . $column . ' IS NOT NULL ORDER BY ' . $column;
        $values = [];

        foreach ($this->database->fetchAll($sql, $this->entityConditions->params()) as $row) {
            $values[] = $row->string('value');
        }

        return $values;
    }

    /**
     * @param string $expression An aggregate over the entity type's columns.
     *
     * @return string The statement that reads it as `total` over the matching rows.
     */
    private function select(string $expression): string
    {
        return 'SELECT ' . $expression . ' AS total FROM ' . $this->entityJoins->from() . $this->entityConditions->sql();
    }
}
