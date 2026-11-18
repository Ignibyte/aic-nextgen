<?php

declare(strict_types=1);

namespace AIC\Entity\Application;

use AIC\Entity\Domain\Condition;
use AIC\Entity\Domain\DeletedScope;
use AIC\Entity\Domain\EntityType;
use InvalidArgumentException;

use function implode;
use function in_array;
use function mb_trim;
use function str_contains;
use function strtr;

/**
 * A query's conditions (#184): each a SQL condition over the entity type's own
 * columns with `?` for its values, joined by AND. Only names the entity type
 * declares reach the SQL; every value is bound.
 */
final class EntityConditions
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
     * Which rows of a soft-deleting type it sees (#309): the live ones unless asked.
     */
    private DeletedScope $deletedScope = DeletedScope::Live;

    /**
     * @param EntityType  $entityType  The entity type queried.
     * @param EntityJoins $entityJoins The query's joins, whose fields a condition may name as `prefix.field`.
     */
    public function __construct(
        private readonly EntityType $entityType,
        private readonly EntityJoins $entityJoins,
    ) {
    }

    /**
     * @param DeletedScope $deletedScope Which rows of a soft-deleting type to see (#309).
     *
     * @throws InvalidArgumentException When it asks for the deleted rows of a type that deletes for good.
     */
    public function scope(DeletedScope $deletedScope): void
    {
        if ($deletedScope === DeletedScope::Deleted && !$this->entityType->lifecycle->isSoft()) {
            throw new InvalidArgumentException('The ' . $this->entityType->table . ' entity deletes for good: it has no deleted rows.');
        }

        $this->deletedScope = $deletedScope;
    }

    /**
     * @param Condition $condition A field compared with a value; null asks whether it is null.
     *
     * @throws InvalidArgumentException When the field is not the entity type's, or null meets an ordering comparison.
     */
    public function add(Condition $condition): void
    {
        $this->clauses[] = $this->clause($condition);
    }

    /**
     * @param string       $field  A field of the entity type.
     * @param list<string> $values The values it may equal; at least one.
     *
     * @throws InvalidArgumentException When the field is not the entity type's, or the list is empty.
     */
    public function within(string $field, array $values): void
    {
        if ($values === []) {
            throw new InvalidArgumentException('An in-list condition takes at least one value.');
        }

        $marks = [];

        foreach ($values as $value) {
            $marks[] = '?';
            $this->params[] = $value;
        }

        $clause = $this->column($field) . ' IN (' . implode(', ', $marks) . ')';
        $this->clauses[] = $clause;
    }

    /**
     * @param list<Condition> $conditions The conditions, any of which may hold; at least one.
     *
     * @throws InvalidArgumentException When the list is empty, or a condition is not valid for the entity type.
     */
    public function any(array $conditions): void
    {
        if ($conditions === []) {
            throw new InvalidArgumentException('An any-of group takes at least one condition.');
        }

        $clauses = [];

        foreach ($conditions as $condition) {
            $clauses[] = $this->clause($condition);
        }

        $group = '(' . implode(' OR ', $clauses) . ')';
        $this->clauses[] = $group;
    }

    /**
     * @param list<string> $fields Text fields of the entity type, any of which may contain the text.
     * @param string       $text   What to find, matched case-insensitively; blank adds no condition.
     *
     * @throws InvalidArgumentException When no field is given, or one is not the entity type's.
     */
    public function search(array $fields, string $text): void
    {
        if ($fields === []) {
            throw new InvalidArgumentException('A search names at least one field.');
        }

        $text = mb_trim($text);

        if ($text === '') {
            return;
        }

        $pattern = '%' . strtr($text, ['\\' => '\\\\', '%' => '\\%', '_' => '\\_']) . '%';
        $clauses = [];

        foreach ($fields as $field) {
            $clauses[] = $this->column($field) . ' ILIKE ?';
            $this->params[] = $pattern;
        }

        $group = '(' . implode(' OR ', $clauses) . ')';
        $this->clauses[] = $group;
    }

    /**
     * @param string          $relation   One of the entity type's relations, not Polymorphic.
     * @param list<Condition> $conditions Conditions on its target's fields, all of which a related row meets.
     * @param bool            $missing    Whether to keep the rows with no such related row, not those with one.
     *
     * @throws InvalidArgumentException When it has no such relation, it cannot filter, or a condition names no field.
     */
    public function related(string $relation, array $conditions, bool $missing): void
    {
        [$exists, $params] = (new RelationSql())->exists($this->entityType, $relation, $conditions);
        $this->subquery(($missing ? 'NOT ' : '') . $exists, $params);
    }

    /**
     * @param string           $clause A whole clause the caller built from identifiers: an EXISTS subquery, or a
     *                                 subquery's link to its owner row (#306).
     * @param list<string|int> $params Its values, in order.
     */
    public function subquery(string $clause, array $params): void
    {
        $this->clauses[] = $clause;
        $this->params = [...$this->params, ...$params];
    }

    /**
     * @param string          $field       A field of the entity type.
     * @param EntityType      $entityType  Another entity type, or the same.
     * @param string          $sourceField A field of it, or its key, whose values the field must be among.
     * @param list<Condition> $conditions  Conditions on its rows; its live rows only, for a soft-deleting one.
     *
     * @throws InvalidArgumentException When a field is not its type's, or a condition is malformed.
     */
    public function valuesIn(string $field, EntityType $entityType, string $sourceField, array $conditions): void
    {
        $column = $this->column($field);
        $entityConditions = new self($entityType, new EntityJoins($entityType));

        foreach ($conditions as $condition) {
            $entityConditions->add($condition);
        }

        if ($sourceField !== $entityType->key) {
            $entityType->field($sourceField);
        }

        $subquery = 'SELECT ' . $entityType->table . '.' . $sourceField . ' FROM ' . $entityType->table . $entityConditions->sql();
        $this->subquery($column . ' IN (' . $subquery . ')', $entityConditions->params());
    }

    /**
     * @param string $field A field of the entity type, its key, or a joined field `prefix.field`.
     *
     * @throws InvalidArgumentException When the entity type, or the join, has no such column.
     *
     * @return string The column, qualified by its table or prefix, safe to write into SQL.
     */
    public function column(string $field): string
    {
        if (str_contains($field, '.')) {
            return $this->entityJoins->column($field);
        }

        if (!in_array($field, $this->entityType->columns(), true)) {
            throw new InvalidArgumentException('The ' . $this->entityType->table . ' entity has no field "' . $field . '".');
        }

        return $this->entityType->table . '.' . $field;
    }

    /**
     * @return bool Whether any condition was added.
     */
    public function hasAny(): bool
    {
        return $this->clauses !== [];
    }

    /**
     * @return string ` WHERE …` with every condition and, for a soft-deleting type, the scope's (#309); empty when
     *                there is none.
     */
    public function sql(): string
    {
        $table = $this->entityType->table;
        $clauses = $this->clauses;
        $scoped = match ($this->deletedScope) {
            DeletedScope::Live => $this->entityType->live($table),
            DeletedScope::All => '',
            DeletedScope::Deleted => $table . '.' . $this->entityType->lifecycle->deletedAt . ' IS NOT NULL',
        };

        if ($scoped !== '') {
            $clauses[] = $scoped;
        }

        return $clauses === []
            ? ''
            : ' WHERE ' . implode(' AND ', $clauses);
    }

    /**
     * @return list<string|int> The conditions' values, in the order of their marks.
     */
    public function params(): array
    {
        return $this->params;
    }

    /**
     * @param Condition $condition A field compared with a value.
     *
     * @throws InvalidArgumentException When the field is not the entity type's, or null meets an ordering comparison.
     *
     * @return string Its SQL; its value, if any, appended to the params.
     */
    private function clause(Condition $condition): string
    {
        $conditionSql = new ConditionSql();
        $column = $this->column($condition->field);
        $value = $condition->value;

        if ($value === null) {
            return $column . $conditionSql->nullTest($condition->comparison);
        }

        $this->params[] = $conditionSql->bound($value);

        return $conditionSql->compared($column, $condition->comparison);
    }
}
