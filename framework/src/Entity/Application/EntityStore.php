<?php

declare(strict_types=1);

namespace AIC\Entity\Application;

use AIC\Database\Contracts\Database;
use AIC\Database\Contracts\QueryException;
use AIC\Database\Contracts\Row;
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
    public function __construct(
        private Database $database,
    ) {
    }

    /**
     * @param EntityType $entityType The entity type.
     *
     * @return EntityQuery A query over its rows.
     */
    public function query(EntityType $entityType): EntityQuery
    {
        return new EntityQuery($this->database, $entityType);
    }

    /**
     * @param EntityType                          $entityType The entity type.
     * @param array<string, string|int|bool|null> $values     The writable fields' values.
     *
     * @throws InvalidArgumentException When a value names a field that is not writable, or does not fit it.
     * @throws QueryException           When the insert fails.
     *
     * @return int The new row's key.
     *
     * @psalm-taint-sink client_upload $values
     */
    public function insert(EntityType $entityType, array $values): int
    {
        $entityValues = new EntityValues($entityType, $values);

        return $this->database->fetchOne($this->insertSql($entityType, $entityValues, ''), $entityValues->params())->int($entityType->key);
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
        $entityValues = new EntityValues($entityType, $values);
        $sql = $this->insertSql($entityType, $entityValues, ' ON CONFLICT (' . self::unique($entityValues, $unique) . ') DO NOTHING');
        $row = $this->database->fetchAll($sql, $entityValues->params())[0] ?? null;

        return $row instanceof Row
            ? $row->int($entityType->key)
            : null;
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
     * @param int                                 $key        The row's key.
     * @param array<string, string|int|bool|null> $values     The writable fields to change.
     *
     * @throws InvalidArgumentException When there is nothing to change, or a value does not fit its field.
     * @throws EntityNotFoundException  When no row has the key.
     * @throws QueryException           When the update fails.
     *
     * @psalm-taint-sink client_upload $values
     */
    public function update(EntityType $entityType, int $key, array $values): void
    {
        if ($this->query($entityType)->where($entityType->key, $key)->changes()->update($values) === 0) {
            throw new EntityNotFoundException('No ' . $entityType->table . ' row has that key.');
        }
    }

    /**
     * @param EntityType $entityType The entity type.
     * @param int        $key        The row's key.
     *
     * @throws EntityNotFoundException When no row has the key.
     * @throws QueryException          When the delete fails.
     */
    public function delete(EntityType $entityType, int $key): void
    {
        $sql = 'DELETE FROM ' . $entityType->table . ' WHERE ' . $entityType->key . ' = ?';

        if ($this->database->execute($sql, [$key]) === 0) {
            throw new EntityNotFoundException('No ' . $entityType->table . ' row has that key.');
        }
    }

    /**
     * @param Closure(): void $work The reads and writes to make together.
     *
     * @throws Throwable Whatever the work throws, after the transaction is rolled back.
     */
    public function transaction(Closure $work): void
    {
        $this->database->beginTransaction();

        try {
            $work();
        } catch (Throwable $throwable) {
            $this->database->rollBack();

            throw $throwable;
        }

        $this->database->commit();
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
        $marks = array_merge(self::marks(count($entityValues->columns()), '?'), self::marks(count($stamped), 'now()'));

        return 'INSERT INTO ' . $entityType->table . ' (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $marks) . ')'
            . $conflict . ' RETURNING ' . $entityType->key;
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

    /**
     * @param int    $count How many.
     * @param string $mark  A placeholder or an expression.
     *
     * @return list<string> The mark, that many times.
     */
    private static function marks(int $count, string $mark): array
    {
        return array_fill(0, $count, $mark);
    }
}
