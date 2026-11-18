<?php

declare(strict_types=1);

namespace AIC\Entity\Application;

use AIC\Database\Contracts\Database;
use AIC\Database\Contracts\QueryException;
use AIC\Entity\Domain\EntityEvent;
use AIC\Entity\Domain\EntityType;
use InvalidArgumentException;

use function array_merge;
use function count;
use function implode;

/**
 * Writes to the rows a query matches (#184): an update of some fields, its
 * UpdatedAt fields set to `now()`, or a delete. Each needs at least one
 * condition, so no caller rewrites a whole table by forgetting one, and each
 * answers how many rows it changed. Since #309 each runs through the
 * EntityLifecycle, which records every changed row's event for an observed
 * type; a soft-deleting type's delete stamps its deleted field, `restore()`
 * clears it and `purge()` deletes for good.
 */
final readonly class EntityChanges
{
    /**
     * Runs each write and records its events (#309).
     */
    private EntityLifecycle $entityLifecycle;

    /**
     * @param Database         $database         The database.
     * @param string           $actor            Who the changes are credited to (#309).
     * @param EntityType       $entityType       The entity type queried.
     * @param EntityJoins      $entityJoins      The query's joins: a change is on one table, so it takes none.
     * @param EntityConditions $entityConditions The query's conditions.
     */
    public function __construct(
        Database $database,
        string $actor,
        private EntityType $entityType,
        private EntityJoins $entityJoins,
        private EntityConditions $entityConditions,
    ) {
        $this->entityLifecycle = new EntityLifecycle($database, $actor);
    }

    /**
     * @param array<string, string|int|bool|null> $values The writable fields to change.
     *
     * @throws InvalidArgumentException When there is nothing to change, the query has no condition, joins a
     *                                  table or is on a view, or a value does not fit its field.
     * @throws QueryException           When the update, or recording it, fails.
     *
     * @return int How many rows it changed.
     *
     * @psalm-taint-sink client_upload $values
     */
    public function update(array $values): int
    {
        $entityValues = new EntityValues($this->entityType, $values);
        $assignments = [];

        foreach ($entityValues->columns() as $column) {
            $assignments[] = $column . ' = ?';
        }

        if ($assignments === []) {
            throw new InvalidArgumentException('An update changes at least one field.');
        }

        $sql = 'UPDATE ' . $this->entityType->table . ' SET ' . $this->stamped($assignments) . $this->where();
        $params = array_merge($entityValues->params(), $this->entityConditions->params());

        return $this->run(EntityEvent::Updated, $sql, $params, $entityValues->columns(), $entityValues->params());
    }

    /**
     * @throws InvalidArgumentException When the query has no condition, joins a table, or is on a view.
     * @throws QueryException           When the delete, or recording it, fails.
     *
     * @return int How many rows it deleted: for a soft-deleting type, how many live rows it stamped.
     */
    public function delete(): int
    {
        $deletedAt = $this->entityType->lifecycle->deletedAt;

        if ($deletedAt === '') {
            return $this->purge();
        }

        $sql = 'UPDATE ' . $this->entityType->table . ' SET ' . $this->stamped([$deletedAt . ' = now()']) . $this->where();

        return $this->run(EntityEvent::Deleted, $sql, $this->entityConditions->params(), [], []);
    }

    /**
     * @throws InvalidArgumentException When the type deletes for good, or the query has no condition, joins a table,
     *                                  or is on a view.
     * @throws QueryException           When the restore, or recording it, fails.
     *
     * @return int How many deleted rows it brought back; the query sees them with `withDeleted(DeletedScope::Deleted)`.
     */
    public function restore(): int
    {
        $deletedAt = $this->entityType->lifecycle->deletedAt;

        if ($deletedAt === '') {
            throw new InvalidArgumentException(
                'The ' . $this->entityType->table . ' entity deletes for good: there is nothing to restore.',
            );
        }

        $sql = 'UPDATE ' . $this->entityType->table . ' SET ' . $this->stamped([$deletedAt . ' = NULL']) . $this->where();

        return $this->run(EntityEvent::Restored, $sql, $this->entityConditions->params(), [], []);
    }

    /**
     * @throws InvalidArgumentException When the query has no condition, joins a table, or is on a view.
     * @throws QueryException           When the delete, or recording it, fails.
     *
     * @return int How many rows it deleted for good; a soft-deleting type's query sees deleted ones
     *             with `withDeleted()`.
     */
    public function purge(): int
    {
        $event = $this->entityType->lifecycle->isSoft()
            ? EntityEvent::Purged
            : EntityEvent::Deleted;
        $sql = 'DELETE FROM ' . $this->entityType->table . $this->where();

        return $this->run($event, $sql, $this->entityConditions->params(), [], []);
    }

    /**
     * @param EntityEvent                      $entityEvent What the statement does.
     * @param string                           $sql         The statement, before its RETURNING.
     * @param list<string|int|float|bool|null> $params      Its values.
     * @param list<string>                     $columns     The fields it writes.
     * @param list<string|int|float|bool|null> $values      Their values.
     *
     * @throws QueryException When the statement, or recording it, fails.
     *
     * @return int How many rows it changed.
     */
    private function run(EntityEvent $entityEvent, string $sql, array $params, array $columns, array $values): int
    {
        $returning = ' RETURNING ' . $this->entityType->table . '.' . $this->entityType->key . '::text AS aic_key';
        $rows = $this->entityLifecycle->write($this->entityType, $entityEvent, $sql . $returning, $params, $columns, $values);

        return count($rows->all());
    }

    /**
     * @param list<string> $assignments The statement's own assignments.
     *
     * @return string Them, then each UpdatedAt field set to `now()`, joined.
     */
    private function stamped(array $assignments): string
    {
        foreach ($this->entityType->updatedAt() as $column) {
            $assignments[] = $column . ' = now()';
        }

        return implode(', ', $assignments);
    }

    /**
     * @throws InvalidArgumentException When the query has no condition, joins a table, or is on a view.
     *
     * @return string The query's WHERE.
     */
    private function where(): string
    {
        if (!$this->entityConditions->hasAny() || $this->entityJoins->hasAny() || $this->entityType->readOnly) {
            throw new InvalidArgumentException('A change by query names a condition, joins no table and is on a table, not a view.');
        }

        return $this->entityConditions->sql();
    }
}
