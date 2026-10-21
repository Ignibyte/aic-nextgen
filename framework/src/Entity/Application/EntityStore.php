<?php

declare(strict_types=1);

namespace AIC\Entity\Application;

use AIC\Database\Contracts\Database;
use AIC\Database\Contracts\QueryException;
use AIC\Entity\Domain\EntityType;
use InvalidArgumentException;

use function array_fill;
use function array_keys;
use function array_merge;
use function count;
use function implode;
use function in_array;
use function is_bool;

/**
 * Rows of entity types in the database: queries, inserts, updates and deletes
 * by key. Values are by field name and only writable fields take them: a
 * string for Text, Date (`YYYY-MM-DD`) and Numeric (a decimal string), a bool
 * for Boolean. A bool is bound as `'true'` or `'false'`, since PHP's PDO
 * binds `false` as an empty string, which Postgres refuses.
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
     * @param EntityType                 $entityType The entity type.
     * @param array<string, string|bool> $values     The writable fields' values; each CreatedAt field gets `now()`.
     *
     * @throws InvalidArgumentException When a value names a field that is not writable, or is of the wrong kind for it.
     * @throws QueryException           When the insert fails.
     *
     * @return int The new row's key.
     */
    public function insert(EntityType $entityType, array $values): int
    {
        $columns = $this->writable($entityType, $values);
        $params = [];

        foreach ($columns as $column) {
            $params[] = self::bound($values[$column]);
        }

        $createdAt = $entityType->createdAt();
        $marks = array_merge(self::marks(count($columns), '?'), self::marks(count($createdAt), 'now()'));
        $sql = 'INSERT INTO ' . $entityType->table . ' (' . implode(', ', array_merge($columns, $createdAt)) . ')'
            . ' VALUES (' . implode(', ', $marks) . ') RETURNING ' . $entityType->key;

        return $this->database->fetchOne($sql, $params)->int($entityType->key);
    }

    /**
     * @param EntityType                 $entityType The entity type.
     * @param int                        $key        The row's key.
     * @param array<string, string|bool> $values     The writable fields to change.
     *
     * @throws InvalidArgumentException When there is nothing to change, or a value names a field that is not writable
     *                                  or is of the wrong kind.
     * @throws EntityNotFoundException  When no row has the key.
     * @throws QueryException           When the update fails.
     */
    public function update(EntityType $entityType, int $key, array $values): void
    {
        $assignments = [];
        $params = [];

        foreach ($this->writable($entityType, $values) as $column) {
            $assignments[] = $column . ' = ?';
            $params[] = self::bound($values[$column]);
        }

        if ($assignments === []) {
            throw new InvalidArgumentException('An update changes at least one field.');
        }

        $params[] = $key;
        $sql = 'UPDATE ' . $entityType->table . ' SET ' . implode(', ', $assignments) . ' WHERE ' . $entityType->key . ' = ?';
        $this->affectOne($entityType, $sql, $params);
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
        $this->affectOne($entityType, 'DELETE FROM ' . $entityType->table . ' WHERE ' . $entityType->key . ' = ?', [$key]);
    }

    /**
     * @param EntityType       $entityType The entity type.
     * @param string           $sql        A statement on one row by key.
     * @param list<string|int> $params     Its values.
     *
     * @throws EntityNotFoundException When it affected no row.
     * @throws QueryException          When it fails.
     */
    private function affectOne(EntityType $entityType, string $sql, array $params): void
    {
        if ($this->database->execute($sql, $params) === 0) {
            throw new EntityNotFoundException('No ' . $entityType->table . ' row has that key.');
        }
    }

    /**
     * @param EntityType                 $entityType The entity type.
     * @param array<string, string|bool> $values     Values by field name.
     *
     * @throws InvalidArgumentException When a name is not a writable field, or its value is of the wrong kind.
     *
     * @return list<string> The names, in order.
     */
    private function writable(EntityType $entityType, array $values): array
    {
        $writable = $entityType->writable();
        $booleans = $entityType->booleans();
        $columns = array_keys($values);

        foreach ($columns as $column) {
            if (!in_array($column, $writable, true)) {
                throw new InvalidArgumentException('The ' . $entityType->table . ' entity has no writable field "' . $column . '".');
            }

            self::checkKind($entityType, $column, is_bool($values[$column]), in_array($column, $booleans, true));
        }

        return $columns;
    }

    /**
     * @param EntityType $entityType The entity type.
     * @param string     $column     A writable field.
     * @param bool       $given      Whether its value is a bool.
     * @param bool       $wanted     Whether it is a Boolean field.
     *
     * @throws InvalidArgumentException When a bool goes to a field that is not Boolean, or a string to one that is.
     */
    private static function checkKind(EntityType $entityType, string $column, bool $given, bool $wanted): void
    {
        if ($given !== $wanted) {
            throw new InvalidArgumentException(
                'The ' . $entityType->table . ' entity\'s field "' . $column . '" takes ' . ($wanted ? 'a bool.' : 'a string.'),
            );
        }
    }

    /**
     * @param string|bool $value A field's value.
     *
     * @return string It as Postgres reads it: a bool as `true` or `false`.
     */
    private static function bound(string|bool $value): string
    {
        if (is_bool($value)) {
            return $value
                ? 'true'
                : 'false';
        }

        return $value;
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
