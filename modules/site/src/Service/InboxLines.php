<?php

declare(strict_types=1);

namespace App\Site\Service;

use AIC\Database\Contracts\QueryException;
use AIC\Notification\Application\Inbox;
use AIC\Notification\Domain\InboxItem;

use function ctype_digit;
use function strlen;

/**
 * The demo user's inbox as lines (#202): a count of the unread, then each
 * notification, newest first, or only those after an id a live view has seen.
 */
final readonly class InboxLines
{
    /**
     * The most notifications a page shows.
     */
    private const int PAGE = 5;

    /**
     * @param Inbox $inbox The user's kept notifications.
     */
    public function __construct(
        private Inbox $inbox,
    ) {
    }

    /**
     * @param string $userId The user.
     * @param string $after  An id from the `after` query field: show only the notifications after it; empty for the
     *                       newest page.
     *
     * @throws QueryException When the notifications cannot be read.
     *
     * @return list<string> The count line, then one line per notification.
     */
    public function lines(string $userId, string $after): array
    {
        $items = ctype_digit($after) && strlen($after) < 10
            ? $this->inbox->newerThan($userId, (int) $after, self::PAGE)
            : $this->inbox->page($userId, 0, self::PAGE);
        $lines = [$this->inbox->unread($userId) . ' unread of ' . $this->inbox->total($userId)];

        foreach ($items as $item) {
            $lines[] = self::line($item);
        }

        return $lines;
    }

    /**
     * @param InboxItem $inboxItem A notification.
     *
     * @return string Its line: id, subject, body, link and whether it is read.
     */
    private static function line(InboxItem $inboxItem): string
    {
        return '#' . $inboxItem->id . ' ' . $inboxItem->subject . ': ' . $inboxItem->body
            . ($inboxItem->link === '' ? '' : ' (' . $inboxItem->link . ')')
            . ' at ' . $inboxItem->sentAt
            . ($inboxItem->read ? ', read' : ', unread');
    }
}
