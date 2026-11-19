<?php

declare(strict_types=1);

namespace AIC\Broadcast\Domain;

use AIC\Schema\Contracts\Migration;
use AIC\Schema\Contracts\Schema;
use Override;

/**
 * The published events (#314): `aic_broadcasts`, one row per event, kept
 * for `BroadcastConfig::$keepSeconds` so a stream's reconnect replays what
 * it missed. A publish also notifies the Postgres channel `aic_broadcasts`,
 * which only wakes the streams; the row is the event.
 */
final readonly class CreateBroadcastTable implements Migration
{
    /**
     * @return string The version.
     */
    #[Override]
    public function version(): string
    {
        return '2026_11_19_000001_create_aic_broadcasts';
    }

    /**
     * @param Schema $schema The schema.
     */
    #[Override]
    public function describe(Schema $schema): void
    {
        $schema->create('aic_broadcasts')
            ->identity('id')
            ->string('channel', 100)
            ->text('data')
            ->timestampTz('published_at')
            ->primary(['id'])
            ->index(['channel', 'id'])
            ->index(['published_at']);
    }
}
