<?php

declare(strict_types=1);

namespace AIC\Notification\Domain;

/**
 * One notification in a user's inbox (#202), as the database channel kept it.
 */
final readonly class InboxItem
{
    /**
     * @param int    $id      Its id, rising with each notification.
     * @param string $subject One line.
     * @param string $body    The text.
     * @param string $link    The site path it is about; empty for none.
     * @param string $sentAt  When it was kept, ISO 8601 in UTC.
     * @param bool   $read    Whether its user has read it.
     */
    public function __construct(
        public int $id,
        public string $subject,
        public string $body,
        public string $link,
        public string $sentAt,
        public bool $read,
    ) {
    }
}
