<?php

declare(strict_types=1);

namespace AIC\Entity\Application;

use AIC\Database\Contracts\Database;
use AIC\Database\Contracts\QueryException;
use AIC\Database\Contracts\Row;
use AIC\Database\Contracts\Rows;
use AIC\Entity\Domain\Comparison;
use AIC\Entity\Domain\Condition;
use AIC\Entity\Domain\EntityType;
use InvalidArgumentException;

/**
 * A query over one entity type: the key and every field of the rows that match
 * every condition (#184: comparisons, in-lists, null tests, any-of groups and a
 * search, each value bound), in `orderBy()` and `orderByDescending()` order,
 * past `offset()` rows and at most `limit()` of them, locked by `forUpdate()`.
 * `totals()` adds the matching rows up and `changes()` writes to them. Its
 * entity type's relations (#306) add a count or a sum per row
 * (`withCount()`, `withSum()`) and filter by related rows (`withExisting()`,
 * `withMissing()`), each a correlated subquery.
 */
final readonly class EntityQuery
{
    use QueryForms;
    use QueryPages;

    /** The tables joined through reference fields (#190). */
    private EntityJoins $entityJoins;

    /** The conditions, joined by AND. */
    private EntityConditions $entityConditions;

    /** Its order, page and lock. */
    private EntityPaging $entityPaging;

    /**
     * @param Database   $database   The database.
     * @param EntityType $entityType The entity type queried.
     * @param string     $actor      Who its changes are credited to (#309).
     */
    public function __construct(
        private Database $database,
        private EntityType $entityType,
        private string $actor = '',
    ) {
        $this->entityJoins = new EntityJoins($entityType);
        $this->entityConditions = new EntityConditions($entityType, $this->entityJoins);
        $this->entityPaging = new EntityPaging();
    }

    /**
     * @param string $field    A Reference field of the entity type.
     * @param string $prefix   What the joined table is called in the query: its fields are `prefix.field` in
     *                         conditions and order, and its columns come back as `prefix_column`.
     * @param bool   $optional Whether a row whose reference matches nothing stays, its joined columns null (a left
     *                         join); an inner join by default.
     *
     * @throws InvalidArgumentException When the field is not a reference, or the prefix is malformed or taken.
     *
     * @return self The query.
     */
    public function with(string $field, string $prefix, bool $optional = false): self
    {
        $this->entityJoins->add($field, $prefix, $optional);

        return $this;
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
        $this->entityPaging->order($this->entityConditions->column($field));

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
        $this->entityPaging->order($this->entityConditions->column($field) . ' DESC');

        return $this;
    }

    /**
     * @param string $relation One of the entity type's relations, not Polymorphic.
     *
     * @throws InvalidArgumentException When it has no such relation, or the relation cannot be counted.
     *
     * @return self The query, each row with `<relation>_count`, an integer (#306).
     */
    public function withCount(string $relation): self
    {
        $this->entityJoins->computed()->relation($relation, '');

        return $this;
    }

    /**
     * @param string $relation One of the entity type's relations, not Polymorphic.
     * @param string $field    A field of its target.
     *
     * @throws InvalidArgumentException When it has no such relation or field, or the relation cannot be summed.
     *
     * @return self The query, each row with `<relation>_sum_<field>`, the sum as text (#306).
     */
    public function withSum(string $relation, string $field): self
    {
        $this->entityJoins->computed()->relation($relation, $field);

        return $this;
    }

    /**
     * @param string          $relation   One of the entity type's relations, not Polymorphic.
     * @param list<Condition> $conditions Conditions on its target's fields, all of which a related row meets.
     *
     * @throws InvalidArgumentException When it has no such relation, it cannot filter, or a condition names no field.
     *
     * @return self The query, keeping the rows with at least one such related row (#306).
     */
    public function withExisting(string $relation, array $conditions = []): self
    {
        $this->entityConditions->related($relation, $conditions, false);

        return $this;
    }

    /**
     * @param string          $relation   One of the entity type's relations, not Polymorphic.
     * @param list<Condition> $conditions Conditions on its target's fields.
     *
     * @throws InvalidArgumentException When it has no such relation, it cannot filter, or a condition names no field.
     *
     * @return self The query, keeping the rows with no such related row (#306).
     */
    public function withMissing(string $relation, array $conditions = []): self
    {
        $this->entityConditions->related($relation, $conditions, true);

        return $this;
    }

    /**
     * @return self The query, locking the rows it reads until the transaction ends (`FOR UPDATE`).
     */
    public function forUpdate(): self
    {
        $this->entityPaging->lock();

        return $this;
    }

    /**
     * @throws QueryException When the query fails.
     *
     * @return Rows The matching rows.
     */
    public function rows(): Rows
    {
        return $this->database->fetchAll($this->selectSql(), $this->selectParams());
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
        return $this->rows()->first();
    }

    /**
     * @return EntityTotals What the matching rows add up to; order, limit and offset do not apply.
     */
    public function totals(): EntityTotals
    {
        return new EntityTotals($this->database, $this->entityJoins, $this->entityConditions);
    }

    /**
     * @return EntityChanges Writes to the matching rows; order, limit and offset do not apply.
     */
    public function changes(): EntityChanges
    {
        return new EntityChanges($this->database, $this->actor, $this->entityType, $this->entityJoins, $this->entityConditions);
    }
}
