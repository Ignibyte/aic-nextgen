<?php

declare(strict_types=1);

namespace AIC\Entity\Application;

use AIC\Database\Contracts\Database;
use AIC\Database\Contracts\QueryException;
use AIC\Database\Contracts\Row;
use AIC\Database\Contracts\Rows;
use AIC\Entity\Domain\EntityType;
use AIC\Entity\Domain\FieldType;
use AIC\Entity\Domain\Relation;
use AIC\Entity\Domain\RelationKind;
use InvalidArgumentException;

use function array_fill;
use function array_values;
use function count;
use function implode;
use function in_array;

/**
 * Loads a relation's rows for a page of owner rows (#306): one `IN (...)`
 * query per relation, one per type for a Polymorphic, grouped in PHP by the
 * owner's key as text. A ManyToMany's rows carry the pivot's own fields as
 * `pivot_<field>`.
 */
final readonly class RelationLoader
{
    /**
     * @param Database $database The database.
     */
    public function __construct(
        private Database $database,
    ) {
    }

    /**
     * @param EntityType $entityType The owner rows' entity type.
     * @param string     $name       One of its relations.
     * @param Rows       $rows       Owner rows, each with its key (and a Polymorphic's type and id fields).
     *
     * @throws InvalidArgumentException When it has no such relation.
     * @throws QueryException           When a query fails, or a row lacks a column the relation reads.
     *
     * @return RelatedRows Each owner's related rows, by its key.
     */
    public function load(EntityType $entityType, string $name, Rows $rows): RelatedRows
    {
        $relation = $entityType->relation($name);
        $target = $relation->target;

        if ($rows->all() === [] || !$target instanceof EntityType) {
            return $relation->kind === RelationKind::Polymorphic
                ? $this->polymorphic($entityType, $relation, $rows)
                : new RelatedRows([]);
        }

        $keys = self::values($rows, $entityType->key, $entityType->keyType);
        $pivot = $relation->pivot;

        return $pivot instanceof EntityType
            ? $this->grouped($this->through($relation, $target, $pivot, $keys), 'pivot_owner', $entityType->keyType)
            : $this->grouped($this->owned($relation, $target, $keys), $relation->key, $entityType->keyType);
    }

    /**
     * @param Relation         $relation   A HasMany or HasOne.
     * @param EntityType       $entityType Its target.
     * @param list<string|int> $keys       The owners' keys.
     *
     * @throws QueryException When the query fails.
     *
     * @return Rows The target rows that hold one of the keys.
     */
    private function owned(Relation $relation, EntityType $entityType, array $keys): Rows
    {
        $table = $entityType->table;
        $sql = 'SELECT ' . (new EntityJoins($entityType))->selected() . ' FROM ' . $table . ' WHERE ' . $table . '.' . $relation->key
            . ' IN (' . self::marks($keys) . ')' . self::live($entityType) . ' ORDER BY ' . $table . '.' . $entityType->key;

        return $this->database->fetchAll($sql, $keys);
    }

    /**
     * @param Relation         $relation A ManyToMany.
     * @param EntityType       $target   Its target.
     * @param EntityType       $pivot    Its pivot.
     * @param list<string|int> $keys     The owners' keys.
     *
     * @throws QueryException When the query fails.
     *
     * @return Rows The target rows the pivot links to the keys, with the owner's key and the pivot's fields.
     */
    private function through(Relation $relation, EntityType $target, EntityType $pivot, array $keys): Rows
    {
        $columns = [(new EntityJoins($target))->selected(), $pivot->table . '.' . $relation->key . ' AS pivot_owner'];

        foreach ($pivot->columns() as $column) {
            if (in_array($column, [$pivot->key, $relation->key, $relation->otherKey], true)) {
                continue;
            }

            $columns[] = $pivot->table . '.' . $column . ' AS pivot_' . $column;
        }

        $join = $target->table . ' ON ' . $target->table . '.' . $target->key . ' = ' . $pivot->table . '.' . $relation->otherKey;
        $sql = 'SELECT ' . implode(', ', $columns) . ' FROM ' . $pivot->table . ' INNER JOIN ' . $join
            . ' WHERE ' . $pivot->table . '.' . $relation->key . ' IN (' . self::marks($keys) . ')' . self::live($target)
            . ' ORDER BY ' . $pivot->table . '.' . $pivot->key;

        return $this->database->fetchAll($sql, $keys);
    }

    /**
     * @param EntityType $owner    The owner rows' type.
     * @param Relation   $relation A Polymorphic.
     * @param Rows       $rows     The owner rows.
     *
     * @throws InvalidArgumentException When the owner has no such id field.
     * @throws QueryException           When a query fails, or a row lacks the type or id field.
     *
     * @return RelatedRows Each owner's one row of the type its type field names.
     */
    private function polymorphic(EntityType $owner, Relation $relation, Rows $rows): RelatedRows
    {
        $idType = $owner->field($relation->key)->type;
        $found = [];

        foreach ($relation->types as $typeName => $entityType) {
            $found[$typeName] = $this->byKey($entityType, self::ofType($rows, $relation, $typeName, $idType));
        }

        $groups = [];

        foreach ($rows as $row) {
            $hit = $found[$row->string($relation->typeField)][self::text($row, $relation->key, $idType)] ?? null;
            $groups[self::text($row, $owner->key, $owner->keyType)] = $hit instanceof Row
                ? [$hit]
                : [];
        }

        return new RelatedRows($groups);
    }

    /**
     * @param EntityType       $entityType One of a Polymorphic's types.
     * @param list<string|int> $keys       Keys of its rows.
     *
     * @throws QueryException When the query fails.
     *
     * @return array<string, Row> Its rows with those keys, by key as text.
     */
    private function byKey(EntityType $entityType, array $keys): array
    {
        if ($keys === []) {
            return [];
        }

        $sql = 'SELECT ' . (new EntityJoins($entityType))->selected() . ' FROM ' . $entityType->table . ' WHERE '
            . $entityType->table . '.' . $entityType->key . ' IN (' . self::marks($keys) . ')' . self::live($entityType);
        $byKey = [];

        foreach ($this->database->fetchAll($sql, $keys) as $row) {
            $byKey[self::text($row, $entityType->key, $entityType->keyType)] = $row;
        }

        return $byKey;
    }

    /**
     * @param Rows      $rows      Related rows.
     * @param string    $column    The column that holds each one's owner key.
     * @param FieldType $fieldType The owner's key type.
     *
     * @throws QueryException When a row lacks the column.
     *
     * @return RelatedRows The rows, by owner key as text.
     */
    private function grouped(Rows $rows, string $column, FieldType $fieldType): RelatedRows
    {
        $groups = [];

        foreach ($rows as $row) {
            $groups[self::text($row, $column, $fieldType)][] = $row;
        }

        return new RelatedRows($groups);
    }

    /**
     * @param Rows      $rows      The owner rows.
     * @param Relation  $relation  A Polymorphic.
     * @param string    $typeName  One of its type names.
     * @param FieldType $fieldType Its id field's type.
     *
     * @throws QueryException When a row lacks the type or id field.
     *
     * @return list<string|int> The ids of the rows whose type field names that type, each once.
     */
    private static function ofType(Rows $rows, Relation $relation, string $typeName, FieldType $fieldType): array
    {
        $ids = [];

        foreach ($rows as $row) {
            if ($row->string($relation->typeField) !== $typeName) {
                continue;
            }

            $ids[self::text($row, $relation->key, $fieldType)] = $fieldType === FieldType::Integer
                ? $row->int($relation->key)
                : $row->string($relation->key);
        }

        return array_values($ids);
    }

    /**
     * @param Rows      $rows      Rows.
     * @param string    $column    A key column.
     * @param FieldType $fieldType Its type.
     *
     * @throws QueryException When a row lacks the column.
     *
     * @return list<string|int> Each row's key, as bound.
     */
    private static function values(Rows $rows, string $column, FieldType $fieldType): array
    {
        $values = [];

        foreach ($rows as $row) {
            $values[] = $fieldType === FieldType::Uuid
                ? $row->string($column)
                : $row->int($column);
        }

        return $values;
    }

    /**
     * @param Row       $row       A row.
     * @param string    $column    A key column.
     * @param FieldType $fieldType Its type: an integer or a uuid.
     *
     * @throws QueryException When the row lacks the column.
     *
     * @return string The key as text.
     */
    private static function text(Row $row, string $column, FieldType $fieldType): string
    {
        return $fieldType === FieldType::Integer || $fieldType === FieldType::Reference
            ? (string) $row->int($column)
            : $row->string($column);
    }

    /**
     * @param list<string|int> $keys The values bound.
     *
     * @return string One `?` per value.
     */
    private static function marks(array $keys): string
    {
        return implode(', ', array_fill(0, count($keys), '?'));
    }

    /**
     * @param EntityType $entityType A relation's target.
     *
     * @return string ` AND <its deleted field> IS NULL` for a soft-deleting target (#309), so a load sees live rows only;
     *                empty for any other.
     */
    private static function live(EntityType $entityType): string
    {
        $live = $entityType->live($entityType->table);

        return $live === ''
            ? ''
            : ' AND ' . $live;
    }
}
