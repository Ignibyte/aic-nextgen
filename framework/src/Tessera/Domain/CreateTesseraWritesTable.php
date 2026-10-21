<?php

declare(strict_types=1);

namespace AIC\Tessera\Domain;

use AIC\Schema\Contracts\Migration;
use AIC\Schema\Contracts\Schema;
use Override;

/**
 * The writes Tessera sent with a `Tessera-Write-Id` (#72, protocol 5.8):
 * each under its session's scope, with its answer once it settled, kept a
 * while so a browser's own repeat is answered, not applied again.
 */
final readonly class CreateTesseraWritesTable implements Migration
{
    /**
     * @return string Its version.
     */
    #[Override]
    public function version(): string
    {
        return '2026_10_21_000006_create_aic_tessera_writes';
    }

    /**
     * @param Schema $schema The schema to describe it on.
     */
    #[Override]
    public function describe(Schema $schema): void
    {
        $schema->create('aic_tessera_writes')
            ->string('scope', 64)
            ->string('write_id', 64)
            ->integer('status')
            ->text('headers')
            ->text('body')
            ->timestampTz('created_at')
            ->primary(['scope', 'write_id'])
            ->index(['created_at']);
    }
}
