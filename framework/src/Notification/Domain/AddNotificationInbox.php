<?php

declare(strict_types=1);

namespace AIC\Notification\Domain;

use AIC\Schema\Contracts\Migration;
use AIC\Schema\Contracts\Schema;
use Override;

/**
 * Makes `aic_notifications` an inbox (#202): the path each notification is
 * about, and when its user read it. Both are null on the rows written before.
 */
final readonly class AddNotificationInbox implements Migration
{
    /**
     * @return string Its version.
     */
    #[Override]
    public function version(): string
    {
        return '2026_10_29_000001_add_notification_inbox';
    }

    /**
     * @param Schema $schema The schema to describe it on.
     */
    #[Override]
    public function describe(Schema $schema): void
    {
        $columnTypes = $schema->types();
        $schema->alter('aic_notifications')
            ->addColumn('link', $columnTypes->text())
            ->nullable()
            ->addColumn('read_at', $columnTypes->timestampTz())
            ->nullable()
            ->index(['user_id', 'id']);
    }
}
