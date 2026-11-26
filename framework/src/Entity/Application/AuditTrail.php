<?php

declare(strict_types=1);

namespace AIC\Entity\Application;

use AIC\Database\Contracts\Database;
use AIC\Database\Contracts\QueryException;
use AIC\Database\Contracts\Rows;
use AIC\Entity\Domain\EntityType;

/**
 * An audited row's history (#309), read from `aic_entity_audit`: each event,
 * who wrote it, the values written (Postgres's text of the jsonb object) and
 * when, oldest first.
 */
final readonly class AuditTrail
{
    /**
     * @param Database $database The database, with the audit table.
     */
    public function __construct(
        private Database $database,
    ) {
    }

    /**
     * @param EntityType $entityType An audited entity type.
     * @param int|string $key        One of its rows' keys, live, soft-deleted or purged.
     *
     * @throws QueryException When the query fails.
     *
     * @return Rows Its events, oldest first: `event`, `actor`, `changes` and `recorded_at`.
     */
    public function history(EntityType $entityType, int|string $key): Rows
    {
        return $this->database->fetchAll(
            'SELECT event, actor, changes, recorded_at FROM aic_entity_audit'
                . ' WHERE entity = ? AND entity_key = ? ORDER BY id',
            [$entityType->table, (string) $key],
        );
    }
}
