<?php

declare(strict_types=1);

namespace AIC\Notification\Application;

use AIC\Database\Contracts\Database;
use AIC\Database\Contracts\QueryException;
use AIC\Database\Contracts\Row;
use AIC\Notification\Domain\InboxItem;

/**
 * A user's notifications as the database channel keeps them (#202): what is
 * unread, a page of them newest first, one by id, and marking them read. Every
 * read and write is scoped to the user, so another user's id finds nothing.
 */
final readonly class Inbox
{
    /**
     * The columns an item is read from.
     */
    private const string COLUMNS = "id, subject, body, coalesce(link, '') AS link, "
        . "to_char(created_at AT TIME ZONE 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS\"Z\"') AS sent_at, "
        . 'CASE WHEN read_at IS NULL THEN 0 ELSE 1 END AS seen';

    /**
     * @param Database $database The database, with the table (CreateNotificationsTable, AddNotificationInbox).
     */
    public function __construct(
        private Database $database,
    ) {
    }

    /**
     * @param string $userId The user.
     *
     * @throws QueryException When the table cannot be read.
     *
     * @return int How many of their notifications they have not read.
     */
    public function unread(string $userId): int
    {
        return $this->database->fetchOne(
            'SELECT count(*)::int AS n FROM aic_notifications WHERE user_id = ? AND read_at IS NULL',
            [$userId],
        )->int('n');
    }

    /**
     * @param string $userId The user.
     *
     * @throws QueryException When the table cannot be read.
     *
     * @return int How many notifications they have.
     */
    public function total(string $userId): int
    {
        return $this->database->fetchOne('SELECT count(*)::int AS n FROM aic_notifications WHERE user_id = ?', [$userId])->int('n');
    }

    /**
     * @param string $userId The user.
     * @param int    $offset How many of the newest to skip.
     * @param int    $limit  The most to give.
     *
     * @throws QueryException When the table cannot be read.
     *
     * @return list<InboxItem> Their notifications, newest first.
     */
    public function page(string $userId, int $offset, int $limit): array
    {
        return $this->items(
            $this->database->fetchAll(
                'SELECT ' . self::COLUMNS . ' FROM aic_notifications WHERE user_id = ? ORDER BY id DESC LIMIT ? OFFSET ?',
                [$userId, $limit, $offset],
            ),
        );
    }

    /**
     * @param string $userId The user.
     * @param int    $id     A notification's id.
     *
     * @throws QueryException When the table cannot be read.
     *
     * @return InboxItem|null It, when it is theirs; null otherwise.
     */
    public function one(string $userId, int $id): ?InboxItem
    {
        $items = $this->items(
            $this->database->fetchAll(
                'SELECT ' . self::COLUMNS . ' FROM aic_notifications WHERE user_id = ? AND id = ?',
                [$userId, $id],
            ),
        );

        return $items[0] ?? null;
    }

    /**
     * @param string $userId The user.
     * @param int    $id     The newest id the caller has seen.
     *
     * @throws QueryException When the table cannot be read.
     *
     * @return list<InboxItem> Their notifications after it, oldest first: what a live view has not shown yet.
     */
    public function newerThan(string $userId, int $id): array
    {
        return $this->items(
            $this->database->fetchAll(
                'SELECT ' . self::COLUMNS . ' FROM aic_notifications WHERE user_id = ? AND id > ? ORDER BY id',
                [$userId, $id],
            ),
        );
    }

    /**
     * @param string $userId The user.
     * @param int    $id     A notification's id.
     *
     * @throws QueryException When the table cannot be written.
     *
     * @return bool Whether it is theirs (read already or not).
     */
    public function markRead(string $userId, int $id): bool
    {
        $this->database->execute(
            'UPDATE aic_notifications SET read_at = now() WHERE user_id = ? AND id = ? AND read_at IS NULL',
            [$userId, $id],
        );

        return $this->one($userId, $id) instanceof InboxItem;
    }

    /**
     * @param string $userId The user.
     *
     * @throws QueryException When the table cannot be written.
     */
    public function markAllRead(string $userId): void
    {
        $this->database->execute(
            'UPDATE aic_notifications SET read_at = now() WHERE user_id = ? AND read_at IS NULL',
            [$userId],
        );
    }

    /**
     * @param list<Row> $rows Rows of `COLUMNS`.
     *
     * @throws QueryException When a column is missing or of another type.
     *
     * @return list<InboxItem> The items.
     */
    private function items(array $rows): array
    {
        $items = [];

        foreach ($rows as $row) {
            $items[] = $this->item($row);
        }

        return $items;
    }

    /**
     * @param Row $row A row of `COLUMNS`.
     *
     * @throws QueryException When a column is missing or of another type.
     *
     * @return InboxItem The item.
     */
    private function item(Row $row): InboxItem
    {
        return new InboxItem(
            $row->int('id'),
            $row->string('subject'),
            $row->string('body'),
            $row->string('link'),
            $row->string('sent_at'),
            $row->int('seen') === 1,
        );
    }
}
