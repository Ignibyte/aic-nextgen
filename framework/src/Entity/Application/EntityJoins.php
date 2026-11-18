<?php

declare(strict_types=1);

namespace AIC\Entity\Application;

use AIC\Entity\Domain\EntityJoin;
use AIC\Entity\Domain\EntityType;
use AIC\Entity\Domain\FieldType;
use AIC\Entity\Domain\JsonValue;
use AIC\Entity\Domain\Similarity;
use AIC\Entity\Domain\Window;
use InvalidArgumentException;

use function array_any;
use function count;
use function explode;
use function implode;
use function in_array;
use function preg_match;

/**
 * A query's joins (#190): each through one of the entity type's reference
 * fields, under a prefix. It writes the query's FROM, every column a row
 * returns (the entity type's, then each joined type's as `prefix_column`),
 * and qualifies a joined field named `prefix.field`.
 */
final class EntityJoins
{
    /**
     * The joins, in the order they were added.
     *
     * @var list<EntityJoin>
     */
    private array $joins = [];

    /**
     * Each relation's count or sum column (#306), as `, (<subquery>) AS <name>`.
     *
     * @var list<string>
     */
    private array $computed = [];

    /**
     * The computed columns' bound values, in the order of their marks (#311).
     *
     * @var list<string>
     */
    private array $computedParams = [];

    /**
     * @param EntityType $entityType The entity type queried.
     */
    public function __construct(
        private readonly EntityType $entityType,
    ) {
    }

    /**
     * @param string $field    A Reference field of the entity type.
     * @param string $prefix   The joined table's name in the query, an identifier other than the table's.
     * @param bool   $optional Whether a row without a match stays (a left join).
     *
     * @throws InvalidArgumentException When the field is not a reference, or the prefix is malformed or taken.
     */
    public function add(string $field, string $prefix, bool $optional): void
    {
        $reference = $this->entityType->field($field);
        $references = $reference->references;

        if ($reference->type !== FieldType::Reference || !$references instanceof EntityType) {
            throw new InvalidArgumentException('The ' . $this->entityType->table . ' entity\'s field "' . $field . '" is no reference.');
        }

        if (preg_match('/^[a-z_][a-z0-9_]{0,62}$/', $prefix) !== 1 || $this->taken($prefix)) {
            throw new InvalidArgumentException(
                'A join prefix is an identifier that no other table of the query uses; got "' . $prefix . '".',
            );
        }

        $entityJoin = new EntityJoin($reference, $references, $prefix, $optional);
        $this->joins[] = $entityJoin;
    }

    /**
     * @param string $field A joined field, `prefix.field`.
     *
     * @throws InvalidArgumentException When no join has the prefix, or its entity type has no such column.
     *
     * @return string The column, safe to write into SQL.
     */
    public function column(string $field): string
    {
        [$prefix, $name] = explode('.', $field, 2) + ['', ''];

        foreach ($this->joins as $join) {
            if ($join->prefix === $prefix && in_array($name, $join->entityType->columns(), true)) {
                return $prefix . '.' . $name;
            }
        }

        throw new InvalidArgumentException('The query has no joined field "' . $field . '".');
    }

    /**
     * @return string The table and each join, for a FROM.
     */
    public function from(): string
    {
        $from = $this->entityType->table;

        foreach ($this->joins as $join) {
            $from .= ($join->optional ? ' LEFT JOIN ' : ' INNER JOIN ') . $join->entityType->table . ' AS '
                . $join->prefix . ' ON ' . $join->prefix . '.' . $join->entityType->key . ' = '
                . $this->entityType->table . '.' . $join->field->name;
        }

        return $from;
    }

    /**
     * @param string $relation One of the entity type's relations, not Polymorphic.
     * @param string $field    A field of its target to sum; empty to count the related rows.
     *
     * @throws InvalidArgumentException When it has no such relation or field, or the relation cannot be a subquery.
     */
    public function compute(string $relation, string $field): void
    {
        $relationSql = new RelationSql();
        $this->computed[] = $field === ''
            ? ', ' . $relationSql->count($this->entityType, $relation) . ' AS ' . $relation . '_count'
            : ', ' . $relationSql->sum($this->entityType, $relation, $field) . ' AS ' . $relation . '_sum_' . $field;
    }

    /**
     * @param list<object> $columns Windows, JsonValues and Similarities (#311).
     *
     * @throws InvalidArgumentException When one is none of these, names a field the type lacks, or its alias is a column.
     */
    public function computeColumns(array $columns): void
    {
        $computedSql = new ComputedSql($this->entityType);
        $computed = $this->windows($computedSql, $columns) + $this->jsonValues($computedSql, $columns)
            + $this->similarities($computedSql, $columns);

        if ($computed !== count($columns)) {
            throw new InvalidArgumentException('A computed column is a Window, a JsonValue or a Similarity.');
        }
    }

    /**
     * @return list<string> The values the selected columns bind, before the conditions' (#311).
     */
    public function params(): array
    {
        return $this->computedParams;
    }

    /**
     * @return string The columns a row returns: the entity type's, then each joined type's as `prefix_column`; a
     *                Json field as Postgres's text of it, the same under PHP and natively (#307); then each count or
     *                sum over a relation (#306) and each computed column (#311).
     */
    public function selected(): string
    {
        $columns = [];

        foreach ($this->entityType->columns() as $column) {
            $read = $this->entityType->read($column);
            $columns[] = $this->entityType->table . '.' . $column . ($read === '' ? '' : $read . ' AS ' . $column);
        }

        foreach ($this->joins as $join) {
            $columns = [...$columns, ...self::joined($join)];
        }

        return implode(', ', $columns) . implode('', $this->computed);
    }

    /**
     * @return bool Whether the query joins any table.
     */
    public function hasAny(): bool
    {
        return $this->joins !== [];
    }

    /**
     * @param ComputedSql  $computedSql Renders each.
     * @param list<object> $columns     The computed columns asked for.
     *
     * @throws InvalidArgumentException When a window names a field the type lacks, or its alias is a column.
     *
     * @return int How many were windows.
     */
    private function windows(ComputedSql $computedSql, array $columns): int
    {
        $before = count($this->computed);

        foreach ($columns as $column) {
            if (!$column instanceof Window) {
                continue;
            }

            $this->computed[] = $computedSql->windowColumn($column);
        }

        return count($this->computed) - $before;
    }

    /**
     * @param ComputedSql  $computedSql Renders each.
     * @param list<object> $columns     The computed columns asked for.
     *
     * @throws InvalidArgumentException When a JSON value's field is not a Json field, or its alias is a column.
     *
     * @return int How many were JSON values.
     */
    private function jsonValues(ComputedSql $computedSql, array $columns): int
    {
        $before = count($this->computed);

        foreach ($columns as $column) {
            if (!$column instanceof JsonValue) {
                continue;
            }

            $this->computed[] = $computedSql->jsonColumn($column);
        }

        return count($this->computed) - $before;
    }

    /**
     * @param ComputedSql  $computedSql Renders each.
     * @param list<object> $columns     The computed columns asked for.
     *
     * @throws InvalidArgumentException When a similarity's field is not a Text field, or its alias is a column.
     *
     * @return int How many were similarities, each text bound in order.
     */
    private function similarities(ComputedSql $computedSql, array $columns): int
    {
        $before = count($this->computed);

        foreach ($columns as $column) {
            if (!$column instanceof Similarity) {
                continue;
            }

            $this->computed[] = $computedSql->similarityColumn($column);
            $this->computedParams[] = $column->text;
        }

        return count($this->computed) - $before;
    }

    /**
     * @param string $prefix A prefix asked for.
     *
     * @return bool Whether the table or a join already uses it.
     */
    private function taken(string $prefix): bool
    {
        if ($prefix === $this->entityType->table) {
            return true;
        }

        return array_any($this->joins, static fn (EntityJoin $entityJoin): bool => $entityJoin->prefix === $prefix);
    }

    /**
     * @param EntityJoin $entityJoin A join.
     *
     * @return list<string> Its type's columns, each as `prefix_column`, a Json field as its text.
     */
    private static function joined(EntityJoin $entityJoin): array
    {
        $columns = [];

        foreach ($entityJoin->entityType->columns() as $column) {
            $read = $entityJoin->entityType->read($column);
            $columns[] = $entityJoin->prefix . '.' . $column . $read . ' AS ' . $entityJoin->prefix . '_' . $column;
        }

        return $columns;
    }
}
