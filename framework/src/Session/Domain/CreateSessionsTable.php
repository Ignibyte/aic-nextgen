<?php

declare(strict_types=1);

namespace AIC\Session\Domain;

use AIC\Schema\Contracts\Migration;
use AIC\Schema\Contracts\Schema;
use Override;

/**
 * The sessions table, `aic_sessions`: an app that uses sessions registers
 * this migration with its own.
 */
final readonly class CreateSessionsTable implements Migration
{
    /**
     * @return string Its version.
     */
    #[Override]
    public function version(): string
    {
        return '2026_10_20_000001_create_aic_sessions';
    }

    /**
     * @param Schema $schema The schema to describe it on.
     */
    #[Override]
    public function describe(Schema $schema): void
    {
        $schema->create('aic_sessions')
            ->string('id', 64)
            ->text('payload')
            ->timestampTz('expires_at')
            ->primary(['id']);
    }
}
