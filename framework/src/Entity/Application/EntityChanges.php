<?php

declare(strict_types=1);

namespace AIC\Entity\Application;

use AIC\Database\Contracts\Database;
use AIC\Database\Contracts\QueryException;
use AIC\Entity\Domain\EntityType;
use InvalidArgumentException;

use function array_merge;
use function implode;

/**
 * Writes to the rows a query matches (#184): an update of some fields, its
 * UpdatedAt fields set to `now()`, or a delete. Each needs at least one
 * condition, so no caller rewrites a whole table by forgetting one, and each
 * answers how many rows it changed.
 */
final readonly class EntityChanges
{
    /**
     * @param Database         $database         The database.
     * @param EntityType       $entityType       The entity type queried.
     * @param EntityConditions $entityConditions The query's conditions.
     */
    public function __construct(
        private Database $database,
        private EntityType $entityType,
        private EntityConditions $entityConditions,
    ) {
    }

    /**
     * @param array<string, string|int|bool|null> $values The writable fields to change.
     *
     * @throws InvalidArgumentException When there is nothing to change, the query has no condition, or a value does
     *                                  not fit its field.
     * @throws QueryException           When the update fails.
     *
     * @return int How many rows it changed.
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

        foreach ($this->entityType->updatedAt() as $column) {
            $assignments[] = $column . ' = now()';
        }

        $sql = 'UPDATE ' . $this->entityType->table . ' SET ' . implode(', ', $assignments) . $this->where();

        return $this->database->execute($sql, array_merge($entityValues->params(), $this->entityConditions->params()));
    }

    /**
     * @throws InvalidArgumentException When the query has no condition.
     * @throws QueryException           When the delete fails.
     *
     * @return int How many rows it deleted.
     */
    public function delete(): int
    {
        return $this->database->execute('DELETE FROM ' . $this->entityType->table . $this->where(), $this->entityConditions->params());
    }

    /**
     * @throws InvalidArgumentException When the query has no condition.
     *
     * @return string The query's WHERE.
     */
    private function where(): string
    {
        if (!$this->entityConditions->hasAny()) {
            throw new InvalidArgumentException('A change by query names at least one condition.');
        }

        return $this->entityConditions->sql();
    }
}
