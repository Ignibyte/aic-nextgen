<?php

declare(strict_types=1);

namespace AIC\Entity\Application;

use AIC\Database\Contracts\Database;
use AIC\Database\Contracts\QueryException;
use AIC\Database\Contracts\Row;
use AIC\Entity\Domain\Comparison;
use AIC\Entity\Domain\Condition;
use AIC\Entity\Domain\EntityType;
use InvalidArgumentException;

use function implode;

/**
 * A query over one entity type: the key and every field of the rows that match
 * every condition (#184: comparisons, in-lists, null tests, any-of groups and a
 * search, each value bound), in `orderBy()` and `orderByDescending()` order,
 * past `offset()` rows and at most `limit()` of them, locked by `forUpdate()`.
 * `totals()` adds the matching rows up and `changes()` writes to them.
 */
final class EntityQuery
{
    /**
     * The conditions, joined by AND.
     */
    private readonly EntityConditions $entityConditions;

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
     * How many matching rows it skips.
     */
    private int $offset = 0;

    /**
     * Whether it locks the rows it reads until the transaction ends.
     */
    private bool $locked = false;

    /**
     * @param Database   $database   The database.
     * @param EntityType $entityType The entity type queried.
     */
    public function __construct(
        private readonly Database $database,
        private readonly EntityType $entityType,
    ) {
        $this->entityConditions = new EntityConditions($entityType);
    }

    /**
     * @param string               $field      A field of the entity type, or its key.
     * @param string|int|bool|null $value      What it is compared with; null tests for null.
     * @param Comparison           $comparison How it is compared; Equals by default.
     *
     * @throws InvalidArgumentException When the entity type has no such field, or null meets an ordering comparison.
     *
     * @return self The query.
     */
    public function where(string $field, string|int|bool|null $value, Comparison $comparison = Comparison::Equals): self
    {
        $this->entityConditions->add(new Condition($field, $value, $comparison));

        return $this;
    }

    /**
     * @param string       $field  A field of the entity type, or its key.
     * @param list<string> $values The text values it may equal; at least one.
     *
     * @throws InvalidArgumentException When the entity type has no such field, or the list is empty.
     *
     * @return self The query.
     */
    public function whereIn(string $field, array $values): self
    {
        $this->entityConditions->within($field, $values);

        return $this;
    }

    /**
     * @param list<Condition> $conditions Conditions any one of which may hold; at least one.
     *
     * @throws InvalidArgumentException When the list is empty, or a condition is not valid for the entity type.
     *
     * @return self The query.
     */
    public function whereAny(array $conditions): self
    {
        $this->entityConditions->any($conditions);

        return $this;
    }

    /**
     * @param list<string> $fields Text fields any of which may contain the text.
     * @param string       $text   What to find, case-insensitively, as typed (`%` and `_` are literal); blank matches
     *                             every row.
     *
     * @throws InvalidArgumentException When no field is given, or one is not the entity type's.
     *
     * @return self The query.
     */
    public function search(array $fields, string $text): self
    {
        $this->entityConditions->search($fields, $text);

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
        $this->order[] = $this->entityConditions->column($field);

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
        $this->order[] = $this->entityConditions->column($field) . ' DESC';

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
     * @param int $count How many matching rows it skips, in its order.
     *
     * @throws InvalidArgumentException When the count is negative.
     *
     * @return self The query.
     */
    public function offset(int $count): self
    {
        if ($count < 0) {
            throw new InvalidArgumentException('A query offset cannot be negative, not ' . $count . '.');
        }

        $this->offset = $count;

        return $this;
    }

    /**
     * @return self The query, locking the rows it reads until the transaction ends (`FOR UPDATE`).
     */
    public function forUpdate(): self
    {
        $this->locked = true;

        return $this;
    }

    /**
     * @throws QueryException When the query fails.
     *
     * @return list<Row> The matching rows.
     */
    public function rows(): array
    {
        $sql = 'SELECT ' . implode(', ', $this->entityType->columns()) . ' FROM ' . $this->entityType->table
            . $this->entityConditions->sql()
            . ($this->order === [] ? '' : ' ORDER BY ' . implode(', ', $this->order))
            . ($this->limit > 0 ? ' LIMIT ' . $this->limit : '')
            . ($this->offset > 0 ? ' OFFSET ' . $this->offset : '')
            . ($this->locked ? ' FOR UPDATE' : '');

        return $this->database->fetchAll($sql, $this->entityConditions->params());
    }

    /**
     * @throws QueryException          When the query fails.
     * @throws EntityNotFoundException When no row matches.
     *
     * @return Row The first matching row.
     */
    public function one(): Row
    {
        $row = $this->first();

        if (!$row instanceof Row) {
            throw new EntityNotFoundException('No ' . $this->entityType->table . ' row matches.');
        }

        return $row;
    }

    /**
     * @throws QueryException When the query fails.
     *
     * @return Row|null The first matching row; null when none matches.
     */
    public function first(): ?Row
    {
        return $this->rows()[0] ?? null;
    }

    /**
     * @return EntityTotals What the matching rows add up to; order, limit and offset do not apply.
     */
    public function totals(): EntityTotals
    {
        return new EntityTotals($this->database, $this->entityType, $this->entityConditions);
    }

    /**
     * @return EntityChanges Writes to the matching rows; order, limit and offset do not apply.
     */
    public function changes(): EntityChanges
    {
        return new EntityChanges($this->database, $this->entityType, $this->entityConditions);
    }
}
