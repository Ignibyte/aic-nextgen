<?php

declare(strict_types=1);

namespace App\Scaffold\Migration;

use AIC\Schema\Contracts\Migration;
use AIC\Schema\Contracts\Schema;
use Override;

/**
 * The `scaffold_events` table, made by bin/aic make:entity (#123) for ScaffoldEvent.
 */
final readonly class CreateScaffoldEventsTable implements Migration
{
    /**
     * @return string The version the ledger records it under.
     */
    #[Override]
    public function version(): string
    {
        return '2026_11_17_000000_create_scaffold_events_table';
    }

    /**
     * @param Schema $schema Where the migration describes its tables.
     */
    #[Override]
    public function describe(Schema $schema): void
    {
        $schema->create('scaffold_events')
            ->column('id', $schema->types()->uuidKey())
            ->text('label')
            ->column('state', $schema->types()->choice(['open', 'shut']))
            ->column('payload', $schema->types()->jsonb())
            ->timestampTz('happened_at')
            ->column('source', $schema->types()->uuid())
            ->timestampTz('created_at')
            ->primary(['id']);
    }
}
