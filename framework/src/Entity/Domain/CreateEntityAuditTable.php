<?php

declare(strict_types=1);

namespace AIC\Entity\Domain;

use AIC\Schema\Contracts\Migration;
use AIC\Schema\Contracts\Schema;
use InvalidArgumentException;
use Override;

/**
 * The audit trail (#309): `aic_entity_audit`, a row per event of an audited
 * entity type, append-only (#310): who wrote, when, the entity, its key, the
 * event and the values written.
 */
final readonly class CreateEntityAuditTable implements Migration
{
    /**
     * @return string The version.
     */
    #[Override]
    public function version(): string
    {
        return '2026_11_18_000001_create_aic_entity_audit';
    }

    /**
     * @param Schema $schema The schema.
     *
     * @throws InvalidArgumentException When a declaration is malformed.
     */
    #[Override]
    public function describe(Schema $schema): void
    {
        $columnTypes = $schema->types();
        $schema->create('aic_entity_audit')
            ->identity('id')
            ->string('entity', 63)
            ->string('entity_key', 64)
            ->column('event', $columnTypes->choice(['created', 'updated', 'deleted', 'restored', 'purged']))
            ->string('actor', 128)
            ->column('changes', $columnTypes->jsonb())
            ->timestampTz('recorded_at')
            ->primary(['id'])
            ->index(['entity', 'entity_key']);
        $schema->appendOnly('aic_entity_audit');
    }
}
