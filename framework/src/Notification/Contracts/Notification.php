<?php

declare(strict_types=1);

namespace AIC\Notification\Contracts;

/**
 * Something to tell a user (#48): who, where to mail them, and what.
 */
final readonly class Notification
{
    /**
     * @param string $userId  The user, for the database channel.
     * @param string $email   Their address, for the mail channel.
     * @param string $subject One line.
     * @param string $body    The text.
     */
    public function __construct(
        public string $userId,
        public string $email,
        public string $subject,
        public string $body,
    ) {
    }
}
