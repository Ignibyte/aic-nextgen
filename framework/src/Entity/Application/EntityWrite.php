<?php

declare(strict_types=1);

namespace AIC\Entity\Application;

use AIC\Entity\Domain\EntityEvent;
use AIC\Entity\Domain\EntityType;

use function implode;

/**
 * One write EntityLifecycle records (#309): the type, the event, and the
 * fields it writes with their values.
 */
final readonly class EntityWrite
{
    /**
     * @param EntityType                       $entityType  The entity type written.
     * @param EntityEvent                      $entityEvent What the write does.
     * @param list<string>                     $columns     The fields it writes, the type's own names.
     * @param list<string|int|float|bool|null> $values      Their values, in the same order.
     */
    public function __construct(
        public EntityType $entityType,
        public EntityEvent $entityEvent,
        public array $columns,
        public array $values,
    ) {
    }

    /**
     * @return string The audit row's INSERT: the entity, the key, the event and the actor bound, then the written
     *                values bound into `jsonb_build_object()` as text (the field names are the type's own, so they
     *                need no quoting), and the time.
     */
    public function auditSql(): string
    {
        $pairs = [];

        foreach ($this->columns as $column) {
            $pairs[] = "'" . $column . "', ?::text";
        }

        return 'INSERT INTO aic_entity_audit (entity, entity_key, event, actor, changes, recorded_at) VALUES (?, ?, ?, ?, '
            . 'jsonb_build_object(' . implode(', ', $pairs) . '), now())';
    }
}
