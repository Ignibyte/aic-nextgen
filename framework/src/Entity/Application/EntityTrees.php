<?php

declare(strict_types=1);

namespace AIC\Entity\Application;

use AIC\Database\Contracts\Database;
use AIC\Database\Contracts\QueryException;
use AIC\Database\Contracts\Rows;
use AIC\Entity\Domain\EntityType;
use AIC\Entity\Domain\FieldType;
use InvalidArgumentException;

/**
 * Reads a tree an entity type keeps in a Reference to itself (#311), such as
 * a category's parent, through one recursive query: a row and its
 * descendants, or a row and its ancestors, each with its `depth` from the
 * row asked for. Live rows only, for a soft-deleting type; a walk stops at
 * depth 64, so a cycle in the data ends it.
 */
final readonly class EntityTrees
{
    /**
     * How deep a walk goes.
     */
    private const int DEPTH = 64;

    /**
     * @param Database $database The database.
     */
    public function __construct(
        private Database $database,
    ) {
    }

    /**
     * @param EntityType $entityType  A type with a Reference to itself.
     * @param string     $parentField That Reference: each row's parent's key, null at a root.
     * @param int|string $key         The row to start at.
     *
     * @throws InvalidArgumentException When the field is not a Reference to the type itself.
     * @throws QueryException           When the query fails.
     *
     * @return Rows The row and every row below it, nearest first (by depth, then key), each with `depth`.
     */
    public function descendants(EntityType $entityType, string $parentField, int|string $key): Rows
    {
        $table = $entityType->table;

        $link = $table . '.' . $this->parent($entityType, $parentField) . ' = aic_tree.' . $entityType->key;

        return $this->walk($entityType, $key, $link);
    }

    /**
     * @param EntityType $entityType  A type with a Reference to itself.
     * @param string     $parentField That Reference.
     * @param int|string $key         The row to start at.
     *
     * @throws InvalidArgumentException When the field is not a Reference to the type itself.
     * @throws QueryException           When the query fails.
     *
     * @return Rows The row and each row above it up to its root, nearest first, each with `depth`.
     */
    public function ancestors(EntityType $entityType, string $parentField, int|string $key): Rows
    {
        $table = $entityType->table;

        $link = $table . '.' . $entityType->key . ' = aic_tree.' . $this->parent($entityType, $parentField);

        return $this->walk($entityType, $key, $link);
    }

    /**
     * @param EntityType $entityType The type.
     * @param int|string $key        The row to start at.
     * @param string     $link       How a row joins the rows already walked.
     *
     * @throws QueryException When the query fails.
     *
     * @return Rows The walk, nearest first.
     */
    private function walk(EntityType $entityType, int|string $key, string $link): Rows
    {
        $table = $entityType->table;
        $selected = (new EntityJoins($entityType))->selected();
        $live = $entityType->live($table);
        $alsoLive = $live === ''
            ? ''
            : ' AND ' . $live;
        $sql = 'WITH RECURSIVE aic_tree AS ('
            . 'SELECT ' . $selected . ', 0 AS depth FROM ' . $table . ' WHERE ' . $table . '.' . $entityType->key . ' = ?' . $alsoLive
            . ' UNION ALL SELECT ' . $selected . ', aic_tree.depth + 1 FROM ' . $table . ' INNER JOIN aic_tree ON ' . $link
            . ' WHERE aic_tree.depth < ' . self::DEPTH . $alsoLive
            . ') SELECT * FROM aic_tree ORDER BY depth, ' . $entityType->key;

        return $this->database->fetchAll($sql, [$key]);
    }

    /**
     * @param EntityType $entityType  The type.
     * @param string     $parentField A field of it.
     *
     * @throws InvalidArgumentException When it is not a Reference to the type itself.
     *
     * @return string The field.
     */
    private function parent(EntityType $entityType, string $parentField): string
    {
        $field = $entityType->field($parentField);
        $references = $field->references;

        if (
            $field->type !== FieldType::Reference
            || !$references instanceof EntityType
            || $references->table !== $entityType->table
        ) {
            throw new InvalidArgumentException(
                'The ' . $entityType->table . ' entity\'s field "' . $parentField . '" is not a reference to itself.',
            );
        }

        return $parentField;
    }
}
