<?php

declare(strict_types=1);

namespace AIC\Notification\Application;

use AIC\Database\Contracts\Database;
use AIC\Database\Contracts\QueryException;
use AIC\Notification\Contracts\Channel;
use AIC\Notification\Contracts\Notification;
use Override;

/**
 * Keeps a notification for its user in `aic_notifications` (#48); `Inbox`
 * reads them back (#202).
 */
final readonly class DatabaseChannel implements Channel
{
    /**
     * @param Database $database The database, with the table (CreateNotificationsTable).
     */
    public function __construct(
        private Database $database,
    ) {
    }

    /**
     * @param Notification $notification What to keep.
     *
     * @throws QueryException When it cannot be stored.
     */
    #[Override]
    public function deliver(Notification $notification): void
    {
        $this->database->execute(
            'INSERT INTO aic_notifications (user_id, subject, body, link, created_at) VALUES (?, ?, ?, ?, now())',
            [$notification->userId, $notification->subject, $notification->body, $notification->link],
        );
    }
}
