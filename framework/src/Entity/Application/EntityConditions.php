<?php

declare(strict_types=1);

namespace AIC\Entity\Application;

use AIC\Entity\Domain\Comparison;
use AIC\Entity\Domain\Condition;
use AIC\Entity\Domain\EntityType;
use InvalidArgumentException;

use function implode;
use function in_array;
use function is_bool;
use function mb_trim;
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
     * @param EntityType $entityType The entity type queried.
     */
    public function __construct(
        private readonly EntityType $entityType,
    ) {
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
     * @param string $field A field of the entity type, or its key.
     *
     * @throws InvalidArgumentException When the entity type has no such column.
     *
     * @return string The column, safe to write into SQL.
     */
    public function column(string $field): string
    {
        if (!in_array($field, $this->entityType->columns(), true)) {
            throw new InvalidArgumentException('The ' . $this->entityType->table . ' entity has no field "' . $field . '".');
        }

        return $field;
    }

    /**
     * @return bool Whether any condition was added.
     */
    public function hasAny(): bool
    {
        return $this->clauses !== [];
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

    /**
     * @param Condition $condition A field compared with a value.
     *
     * @throws InvalidArgumentException When the field is not the entity type's, or null meets an ordering comparison.
     *
     * @return string Its SQL; its value, if any, appended to the params.
     */
    private function clause(Condition $condition): string
    {
        $column = $this->column($condition->field);
        $value = $condition->value;

        if ($value === null) {
            return $column . self::nullTest($condition->comparison);
        }

        $this->params[] = self::bound($value);

        return $column . ' ' . $condition->comparison->value . ' ?';
    }

    /**
     * @param string|int|bool $value A condition's value.
     *
     * @return string|int It as bound: a bool as `true` or `false`.
     */
    private static function bound(string|int|bool $value): string|int
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
    private static function nullTest(Comparison $comparison): string
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
