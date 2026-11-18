<?php

declare(strict_types=1);

namespace AIC\Entity\Application;

use AIC\Database\Contracts\Database;
use AIC\Database\Contracts\QueryException;
use AIC\Database\Contracts\Rows;
use AIC\Entity\Domain\EntityType;
use Closure;
use InvalidArgumentException;
use Throwable;

use function array_fill;
use function array_merge;
use function count;
use function implode;
use function in_array;

/**
 * Rows of entity types in the database: queries, inserts (also if absent, or
 * as an upsert on a unique field), updates and deletes by key, and a
 * transaction around a closure. Values are by field name and only writable
 * fields take them, each checked against its field (`EntityValues`, #184). A
 * bool is bound as `'true'` or `'false'`, since PHP's PDO binds `false` as an
 * empty string, which Postgres refuses. CreatedAt fields are `now()` at insert,
 * UpdatedAt fields at insert and at every update.
 */
final readonly class EntityStore
{
    /**
     * @param Database $database The database.
     */
    /**
     * Runs each write and records its event (#309).
     */
    private EntityLifecycle $entityLifecycle;

    /**
     * @param Database $database The database.
     * @param string   $actor    Who writes, for the audit trail (#309): a signed-in user's id; empty for the app.
     */
    public function __construct(
        private Database $database,
        private string $actor = '',
    ) {
        $this->entityLifecycle = new EntityLifecycle($database, $actor);
    }

    /**
     * @param string $actor Who writes: the signed-in user's id, as `Actor::$userId` holds it (#309).
     *
     * @return self A store whose writes the audit trail credits to that actor.
     */
    public function withActor(string $actor): self
    {
        return new self($this->database, $actor);
    }

    /**
     * @param EntityType $entityType The entity type.
     *
     * @return EntityQuery A query over its rows: a soft-deleting type's live ones unless it asks (#309).
     */
    public function query(EntityType $entityType): EntityQuery
    {
        return new EntityQuery($this->database, $entityType, $this->actor);
    }

    /**
     * @param EntityType                          $entityType The entity type.
     * @param array<string, string|int|bool|null> $values     The writable fields' values.
     *
     * @throws InvalidArgumentException When the type is keyed by uuid, or a value names a field that is not writable,
     *                                  or does not fit it.
     * @throws QueryException           When the insert fails.
     *
     * @return int The new row's key.
     *
     * @psalm-taint-sink client_upload $values
     */
    public function insert(EntityType $entityType, array $values): int
    {
        (new EntityKeys())->integerKeyed($entityType);
        $key = $this->inserted($entityType, new EntityValues($entityType, $values, $entityType->initialState()), '');

        if ($key === null) {
            throw new QueryException('The insert returned no row.');
        }

        return (int) $key;
    }

    /**
     * @param EntityType                          $entityType A uuid-keyed entity type (#307).
     * @param array<string, string|int|bool|null> $values     The writable fields' values.
     *
     * @throws InvalidArgumentException When the type is keyed by an integer, or a value names a field that is not
     *                                  writable, or does not fit it.
     * @throws QueryException           When the insert fails.
     *
     * @return string The new row's key, the uuid the database made.
     *
     * @psalm-taint-sink client_upload $values
     */
    public function insertUuid(EntityType $entityType, array $values): string
    {
        (new EntityKeys())->uuidKeyed($entityType);
        $key = $this->inserted($entityType, new EntityValues($entityType, $values, $entityType->initialState()), '');

        if ($key === null) {
            throw new QueryException('The insert returned no row.');
        }

        return $key;
    }

    /**
     * @param EntityType                          $entityType The entity type.
     * @param array<string, string|int|bool|null> $values     The writable fields' values.
     * @param string                              $unique     A written field the table holds unique.
     *
     * @throws InvalidArgumentException When a value does not fit its field, or the unique field is not written.
     * @throws QueryException           When the insert fails.
     *
     * @return int|null The new row's key; null when a row already holds the unique value.
     */
    public function insertIfAbsent(EntityType $entityType, array $values, string $unique): ?int
    {
        (new EntityKeys())->integerKeyed($entityType);
        $entityValues = new EntityValues($entityType, $values, $entityType->initialState());
        $key = $this->inserted($entityType, $entityValues, ' ON CONFLICT (' . self::unique($entityValues, $unique) . ') DO NOTHING');

        return $key === null
            ? null
            : (int) $key;
    }

    /**
     * @param EntityType                          $entityType The entity type.
     * @param array<string, string|int|bool|null> $values     The writable fields' values.
     * @param string                              $unique     A written field the table holds unique.
     *
     * @throws InvalidArgumentException When a value does not fit its field, or the unique field is not written.
     * @throws QueryException           When the upsert fails.
     */
    public function upsert(EntityType $entityType, array $values, string $unique): void
    {
        if ($entityType->lifecycle->isObserved() || $entityType->stateField() !== '') {
            throw new InvalidArgumentException(
                'The ' . $entityType->table . ' entity records its events or keeps a state;'
                . ' an upsert cannot tell a create from an update.',
            );
        }

        $entityValues = new EntityValues($entityType, $values);
        $column = self::unique($entityValues, $unique);
        $assignments = [];

        foreach ($entityValues->columns() as $written) {
            $assignments[] = $written . ' = EXCLUDED.' . $written;
        }

        foreach ($entityType->updatedAt() as $updatedAt) {
            $assignments[] = $updatedAt . ' = now()';
        }

        $sql = $this->insertSql($entityType, $entityValues, ' ON CONFLICT (' . $column . ') DO UPDATE SET ' . implode(', ', $assignments));

        $this->database->execute($sql, $entityValues->params());
    }

    /**
     * @param EntityType                          $entityType The entity type.
     * @param int|string                          $key        The row's key: an int, or a uuid for a uuid-keyed type (#307).
     * @param array<string, string|int|bool|null> $values     The writable fields to change.
     *
     * @throws InvalidArgumentException When the key is not of the type's key type, there is nothing to change, or a
     *                                  value does not fit its field.
     * @throws EntityNotFoundException  When no row has the key.
     * @throws QueryException           When the update fails.
     *
     * @psalm-taint-sink client_upload $values
     */
    public function update(EntityType $entityType, int|string $key, array $values): void
    {
        if (!(new EntityKeys())->isKey($entityType, $key)) {
            throw new InvalidArgumentException('That is not a key of the ' . $entityType->table . ' entity.');
        }

        if ($this->query($entityType)->where($entityType->key, $key)->changes()->update($values) === 0) {
            throw new EntityNotFoundException('No ' . $entityType->table . ' row has that key.');
        }
    }

    /**
     * @param EntityType $entityType The entity type.
     * @param int|string $key        The row's key: an int, or a uuid for a uuid-keyed type (#307).
     *
     * @throws EntityNotFoundException When no row has the key, or it is not of the type's key type.
     * @throws QueryException          When the delete fails.
     */
    public function delete(EntityType $entityType, int|string $key): void
    {
        if (
            !(new EntityKeys())->isKey($entityType, $key)
            || $this->query($entityType)->where($entityType->key, $key)->changes()->delete() === 0
        ) {
            throw new EntityNotFoundException('No ' . $entityType->table . ' row has that key.');
        }
    }

    /**
     * @param EntityType $entityType The owner rows' entity type.
     * @param string     $name       One of its relations (#306).
     * @param Rows       $rows       A page of owner rows, as a query read them.
     *
     * @throws InvalidArgumentException When it has no such relation.
     * @throws QueryException           When a query fails, or a row lacks a column the relation reads.
     *
     * @return RelatedRows Each owner's related rows, by its key: one query per relation, never one per row.
     */
    public function related(EntityType $entityType, string $name, Rows $rows): RelatedRows
    {
        return (new RelationLoader($this->database))->load($entityType, $name, $rows);
    }

    /**
     * @param Closure(): void $work The reads and writes to make together.
     *
     * @throws Throwable Whatever the work throws, after the transaction is rolled back.
     */
    public function transaction(Closure $work): void
    {
        $this->database->beginTransaction();
        $committed = false;

        try {
            $work();
            $this->database->commit();
            $committed = true;
        } finally {
            if (!$committed && $this->database->inTransaction()) {
                $this->database->rollBack();
            }
        }
    }

    /**
     * @param EntityType   $entityType   The entity type.
     * @param EntityValues $entityValues The checked values.
     * @param string       $conflict     An `ON CONFLICT` clause, or empty.
     *
     * @return string The INSERT, its CreatedAt and UpdatedAt fields `now()`, returning the key.
     */
    private function insertSql(EntityType $entityType, EntityValues $entityValues, string $conflict): string
    {
        $stamped = array_merge($entityType->createdAt(), $entityType->updatedAt());
        $columns = array_merge($entityValues->columns(), $stamped);
        $marks = array_merge(array_fill(0, count($entityValues->columns()), '?'), array_fill(0, count($stamped), 'now()'));

        return 'INSERT INTO ' . $entityType->table . ' (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $marks) . ')'
            . $conflict . ' RETURNING ' . $entityType->key . ', ' . $entityType->key . '::text AS aic_key';
    }

    /**
     * @param EntityType   $entityType   The entity type.
     * @param EntityValues $entityValues The checked values.
     * @param string       $conflict     An `ON CONFLICT` clause, or empty.
     *
     * @throws QueryException When the insert, its audit row or a listener's job fails (#309).
     *
     * @return string|null The new row's key, as the text the database returned; null when a conflict left the table
     *                     as it was.
     */
    private function inserted(EntityType $entityType, EntityValues $entityValues, string $conflict): ?string
    {
        $sql = $this->insertSql($entityType, $entityValues, $conflict);
        $params = $entityValues->params();

        foreach ($this->entityLifecycle->insert($entityType, $sql, $params, $entityValues->columns()) as $row) {
            return $row->string('aic_key');
        }

        return null;
    }

    /**
     * @param EntityValues $entityValues The checked values.
     * @param string       $unique       The field named unique.
     *
     * @throws InvalidArgumentException When the values do not write it.
     *
     * @return string The field, safe to write into SQL.
     */
    private static function unique(EntityValues $entityValues, string $unique): string
    {
        if (!in_array($unique, $entityValues->columns(), true)) {
            throw new InvalidArgumentException('The unique field "' . $unique . '" is not among the values written.');
        }

        return $unique;
    }
}
