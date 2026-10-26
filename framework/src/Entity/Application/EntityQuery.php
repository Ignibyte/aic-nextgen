<?php

declare(strict_types=1);

namespace AIC\Entity\Application;

use AIC\Database\Contracts\Database;
use AIC\Database\Contracts\QueryException;
use AIC\Database\Contracts\Row;
use AIC\Entity\Domain\EntityType;
use InvalidArgumentException;

use function implode;
use function in_array;

/**
 * A query over one entity type: the key and every field of the rows that match
 * each `where()` (equality, with bound values), in `orderBy()` and
 * `orderByDescending()` order, at most `limit()` of them.
 */
final class EntityQuery
{
    /**
     * The `column = ?` conditions, joined by AND.
     *
     * @var list<string>
     */
    private array $conditions = [];

    /**
     * The conditions' values, in order.
     *
     * @var list<string|int>
     */
    private array $params = [];

    /**
     * The columns to sort by, each ascending or with `DESC`.
     *
     * @var list<string>
     */
    private array $order = [];

    /**
     * How many rows it returns at most; 0 for no limit.
     */
    private int $limit = 0;

    /**
     * @param Database   $database   The database.
     * @param EntityType $entityType The entity type queried.
     */
    public function __construct(
        private readonly Database $database,
        private readonly EntityType $entityType,
    ) {
    }

    /**
     * @param string     $field A column of the entity type.
     * @param string|int $value The value it must equal.
     *
     * @throws InvalidArgumentException When the entity type has no such column.
     *
     * @return self The query.
     */
    public function where(string $field, string|int $value): self
    {
        $this->conditions[] = $this->column($field) . ' = ?';
        $this->params[] = $value;

        return $this;
    }

    /**
     * @param string $field A column of the entity type, sorted ascending.
     *
     * @throws InvalidArgumentException When the entity type has no such column.
     *
     * @return self The query.
     */
    public function orderBy(string $field): self
    {
        $this->order[] = $this->column($field);

        return $this;
    }

    /**
     * @param string $field A column of the entity type, sorted descending.
     *
     * @throws InvalidArgumentException When the entity type has no such column.
     *
     * @return self The query.
     */
    public function orderByDescending(string $field): self
    {
        $this->order[] = $this->column($field) . ' DESC';

        return $this;
    }

    /**
     * @param int $count How many rows it returns at most.
     *
     * @throws InvalidArgumentException When the count is below 1.
     *
     * @return self The query.
     */
    public function limit(int $count): self
    {
        if ($count < 1) {
            throw new InvalidArgumentException('A query limit must be at least 1, not ' . $count . '.');
        }

        $this->limit = $count;

        return $this;
    }

    /**
     * @throws QueryException When the query fails.
     *
     * @return list<Row> The matching rows.
     */
    public function rows(): array
    {
        return $this->database->fetchAll($this->sql(), $this->params);
    }

    /**
     * @throws QueryException          When the query fails.
     * @throws EntityNotFoundException When no row matches.
     *
     * @return Row The first matching row.
     */
    public function one(): Row
    {
        $row = $this->rows()[0] ?? null;

        if (!$row instanceof Row) {
            throw new EntityNotFoundException('No ' . $this->entityType->table . ' row matches.');
        }

        return $row;
    }

    /**
     * @return string The SELECT statement.
     */
    private function sql(): string
    {
        $sql = 'SELECT ' . implode(', ', $this->entityType->columns()) . ' FROM ' . $this->entityType->table;

        if ($this->conditions !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $this->conditions);
        }

        if ($this->order !== []) {
            $sql .= ' ORDER BY ' . implode(', ', $this->order);
        }

        if ($this->limit > 0) {
            $sql .= ' LIMIT ' . $this->limit;
        }

        return $sql;
    }

    /**
     * @param string $field A column name.
     *
     * @throws InvalidArgumentException When the entity type has no such column.
     *
     * @return string The column.
     */
    private function column(string $field): string
    {
        if (!in_array($field, $this->entityType->columns(), true)) {
            throw new InvalidArgumentException('The ' . $this->entityType->table . ' entity has no field "' . $field . '".');
        }

        return $field;
    }
}
