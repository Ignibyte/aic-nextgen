<?php

declare(strict_types=1);

namespace AIC\Entity\Application;

use AIC\Entity\Domain\EntityType;
use AIC\Entity\Domain\FieldType;
use AIC\Entity\Domain\JsonValue;
use AIC\Entity\Domain\Similarity;
use AIC\Entity\Domain\Window;
use InvalidArgumentException;

use function count;
use function implode;

/**
 * The columns a query computes past its fields (moved out of `EntityJoins` by
 * #329): a count or a sum over a relation (#306), a window, a JSON value or a
 * similarity (#311), each with its name, its type and its bound values.
 */
final class ComputedColumns
{
    /**
     * Each column, as `, <expression> AS <name>`.
     *
     * @var list<string>
     */
    private array $columns = [];

    /**
     * Their bound values, in the order of their marks.
     *
     * @var list<string>
     */
    private array $params = [];

    /**
     * Their names, in order.
     *
     * @var list<string>
     */
    private array $names = [];

    /**
     * Their types, in order: an Integer, a Numeric or a Text.
     *
     * @var list<FieldType>
     */
    private array $types = [];

    /**
     * @param EntityType $entityType The entity type queried.
     */
    public function __construct(
        private readonly EntityType $entityType,
    ) {
    }

    /**
     * @param string $relation One of the entity type's relations, not Polymorphic.
     * @param string $field    A field of its target to sum; empty to count the related rows.
     *
     * @throws InvalidArgumentException When it has no such relation or field, or the relation cannot be a subquery.
     */
    public function relation(string $relation, string $field): void
    {
        $relationSql = new RelationSql();

        if ($field === '') {
            $name = $relation . '_count';
            $this->push($name, FieldType::Integer, ', ' . $relationSql->count($this->entityType, $relation) . ' AS ' . $name);

            return;
        }

        $name = $relation . '_sum_' . $field;
        $this->push($name, FieldType::Numeric, ', ' . $relationSql->sum($this->entityType, $relation, $field) . ' AS ' . $name);
    }

    /**
     * @param list<object> $columns Windows, JsonValues and Similarities (#311).
     *
     * @throws InvalidArgumentException When one is none of these, names a field the type lacks, or its alias is a column.
     */
    public function add(array $columns): void
    {
        $computedSql = new ComputedSql($this->entityType);
        $computed = $this->windows($computedSql, $columns) + $this->jsonValues($computedSql, $columns)
            + $this->similarities($computedSql, $columns);

        if ($computed !== count($columns)) {
            throw new InvalidArgumentException('A computed column is a Window, a JsonValue or a Similarity.');
        }
    }

    /**
     * @return string Every column, each after a comma; empty for none.
     */
    public function sql(): string
    {
        return implode('', $this->columns);
    }

    /**
     * @return list<string> The values they bind.
     */
    public function params(): array
    {
        return $this->params;
    }

    /**
     * @return list<string> Their names, in order.
     */
    public function names(): array
    {
        return $this->names;
    }

    /**
     * @return list<FieldType> Their types, in the order of their names.
     */
    public function types(): array
    {
        return $this->types;
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
        $before = count($this->columns);

        foreach ($columns as $column) {
            if (!$column instanceof Window) {
                continue;
            }

            $this->push($column->alias, FieldType::Integer, $computedSql->windowColumn($column));
        }

        return count($this->columns) - $before;
    }

    /**
     * @param ComputedSql  $computedSql Renders each.
     * @param list<object> $columns     The computed columns asked for.
     *
     * @throws InvalidArgumentException When a JSON value's field is not a Json field, or its alias is a column.
     *
     * @return int How many were JSON values, each path bound in order.
     */
    private function jsonValues(ComputedSql $computedSql, array $columns): int
    {
        $before = count($this->columns);

        foreach ($columns as $column) {
            if (!$column instanceof JsonValue) {
                continue;
            }

            $this->push($column->alias, FieldType::Text, $computedSql->jsonColumn($column));
            $this->params[] = '{' . implode(',', $column->path) . '}';
        }

        return count($this->columns) - $before;
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
        $before = count($this->columns);

        foreach ($columns as $column) {
            if (!$column instanceof Similarity) {
                continue;
            }

            $this->push($column->alias, FieldType::Numeric, $computedSql->similarityColumn($column));
            $this->params[] = $column->text;
        }

        return count($this->columns) - $before;
    }

    /**
     * @param string    $name      A column's name.
     * @param FieldType $fieldType Its type.
     * @param string    $column    It, after a comma.
     */
    private function push(string $name, FieldType $fieldType, string $column): void
    {
        $this->names[] = $name;
        $this->types[] = $fieldType;
        $this->columns[] = $column;
    }
}
