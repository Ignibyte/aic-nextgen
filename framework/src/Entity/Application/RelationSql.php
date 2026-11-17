<?php

declare(strict_types=1);

namespace AIC\Entity\Application;

use AIC\Entity\Domain\Condition;
use AIC\Entity\Domain\EntityType;
use AIC\Entity\Domain\Relation;
use InvalidArgumentException;

use function in_array;

/**
 * A relation's correlated subqueries for an owner's query (#306): a count, a
 * sum, and an EXISTS with conditions on the target's fields, each bound like
 * the query's own. The target (and a ManyToMany's pivot) is named by its table,
 * so a relation to the owner's own table is refused, as is a Polymorphic one,
 * which loads but does not filter or count.
 */
final readonly class RelationSql
{
    /**
     * @param EntityType $entityType The query's entity type.
     * @param string     $name       One of its relations.
     *
     * @throws InvalidArgumentException When it has no such relation, or the relation cannot be counted.
     *
     * @return string `(SELECT count(*) ...)`, an integer per owner row.
     */
    public function count(EntityType $entityType, string $name): string
    {
        [$scope] = $this->scope($entityType, $entityType->relation($name), []);

        return '(SELECT count(*)::integer' . $scope . ')';
    }

    /**
     * @param EntityType $entityType The query's entity type.
     * @param string     $name       One of its relations.
     * @param string     $field      A field of the relation's target.
     *
     * @throws InvalidArgumentException When it has no such relation or field, or the relation cannot be summed.
     *
     * @return string `(SELECT coalesce(sum(...), 0)::text ...)`, the sum as text, 0 for none.
     */
    public function sum(EntityType $entityType, string $name, string $field): string
    {
        $relation = $entityType->relation($name);
        $target = $this->target($entityType, $relation);

        if (!in_array($field, $target->columns(), true)) {
            throw new InvalidArgumentException('The ' . $target->table . ' entity has no field "' . $field . '" to sum.');
        }

        [$scope] = $this->scope($entityType, $relation, []);

        return '(SELECT coalesce(sum(' . $target->table . '.' . $field . '), 0)::text' . $scope . ')';
    }

    /**
     * @param EntityType      $entityType The query's entity type.
     * @param string          $name       One of its relations.
     * @param list<Condition> $conditions Conditions on the target's fields, all of which a related row meets.
     *
     * @throws InvalidArgumentException When it has no such relation, the relation cannot be filtered by, or a condition
     *                                  names no target field.
     *
     * @return array{string, list<string|int>} `EXISTS (SELECT 1 ...)` and its values.
     */
    public function exists(EntityType $entityType, string $name, array $conditions): array
    {
        [$scope, $params] = $this->scope($entityType, $entityType->relation($name), $conditions);

        return ['EXISTS (SELECT 1' . $scope . ')', $params];
    }

    /**
     * @param EntityType      $entityType The owner.
     * @param Relation        $relation   One of its relations.
     * @param list<Condition> $conditions Conditions on the target's fields.
     *
     * @throws InvalidArgumentException When the relation cannot be a subquery, or a condition names no target field.
     *
     * @return array{string, list<string|int>} ` FROM ... WHERE <link> AND <conditions>` and its values.
     */
    private function scope(EntityType $entityType, Relation $relation, array $conditions): array
    {
        $target = $this->target($entityType, $relation);
        $entityConditions = new EntityConditions($target, new EntityJoins($target));
        $through = $relation->pivot instanceof EntityType
            ? $relation->pivot->table
            : $target->table;
        $entityConditions->subquery($through . '.' . $relation->key . ' = ' . $entityType->table . '.' . $entityType->key, []);

        foreach ($conditions as $condition) {
            $entityConditions->add($condition);
        }

        return [' FROM ' . $this->from($relation, $target) . $entityConditions->sql(), $entityConditions->params()];
    }

    /**
     * @param Relation   $relation   A relation that is not Polymorphic.
     * @param EntityType $entityType Its target.
     *
     * @return string Its tables: the target's, or the pivot's joined to the target's.
     */
    private function from(Relation $relation, EntityType $entityType): string
    {
        $pivot = $relation->pivot;

        return $pivot instanceof EntityType
            ? $pivot->table . ' INNER JOIN ' . $entityType->table . ' ON ' . $entityType->table . '.' . $entityType->key . ' = '
                . $pivot->table . '.' . $relation->otherKey
            : $entityType->table;
    }

    /**
     * @param EntityType $entityType The owner.
     * @param Relation   $relation   One of its relations.
     *
     * @throws InvalidArgumentException When it is Polymorphic, or its target or pivot is the owner's own table.
     *
     * @return EntityType Its target.
     */
    private function target(EntityType $entityType, Relation $relation): EntityType
    {
        $target = $relation->target;
        $pivot = $relation->pivot;
        $tables = [
            $target instanceof EntityType ? $target->table : '',
            $pivot instanceof EntityType ? $pivot->table : '',
        ];

        if (!$target instanceof EntityType || in_array($entityType->table, $tables, true)) {
            throw new InvalidArgumentException('Relation "' . $relation->name . '" cannot be a subquery: it is polymorphic,'
                . ' or relates the ' . $entityType->table . ' table to itself.');
        }

        return $target;
    }
}
