<?php

declare(strict_types=1);

namespace AIC\Notification\Application;

use AIC\Database\Contracts\Database;
use AIC\Database\Contracts\QueryException;
use AIC\Database\Contracts\Row;
use AIC\Notification\Contracts\Channel;
use AIC\Notification\Contracts\Notification;
use Override;

use function array_map;

/**
 * Keeps a notification for its user in `aic_notifications` (#48), and reads
 * a user's latest back.
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
            'INSERT INTO aic_notifications (user_id, subject, body, created_at) VALUES (?, ?, ?, now())',
            [$notification->userId, $notification->subject, $notification->body],
        );
    }

    /**
     * @param string $userId A user.
     * @param int    $limit  The most to read.
     *
     * @throws QueryException When the table cannot be read.
     *
     * @return list<string> Their latest notifications, newest first, as `subject: body`.
     */
    public function latest(string $userId, int $limit): array
    {
        return array_map(
            static fn (Row $row): string => $row->string('line'),
            $this->database->fetchAll(
                "SELECT subject || ': ' || body AS line FROM aic_notifications WHERE user_id = ? ORDER BY id DESC LIMIT ?",
                [$userId, $limit],
            ),
        );
    }
}
