<?php

declare(strict_types=1);

namespace AIC\Notification\Domain;

use AIC\Schema\Contracts\Migration;
use AIC\Schema\Contracts\Schema;
use Override;

/**
 * The database channel's table (#48), `aic_notifications`: an app that
 * notifies registers this migration with its own.
 */
final readonly class CreateNotificationsTable implements Migration
{
    /**
     * @return string Its version.
     */
    #[Override]
    public function version(): string
    {
        return '2026_10_20_000006_create_notifications';
    }

    /**
     * @param Schema $schema The schema to describe it on.
     */
    #[Override]
    public function describe(Schema $schema): void
    {
        $schema->create('aic_notifications')
            ->identity('id')
            ->string('user_id', 100)
            ->text('subject')
            ->text('body')
            ->timestampTz('created_at')
            ->primary(['id']);
    }
}
