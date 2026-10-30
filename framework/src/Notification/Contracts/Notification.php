<?php

declare(strict_types=1);

namespace AIC\Notification\Contracts;

/**
 * Something to tell a user (#48): who, where to mail them, what, and where on
 * the site it is about (#202).
 */
final readonly class Notification
{
    /**
     * @param string $userId  The user, for the database channel.
     * @param string $email   Their address, for the mail channel.
     * @param string $subject One line.
     * @param string $body    The text.
     * @param string $link    The site path it is about, which an inbox links to (#202); empty for none.
     *
     * @psalm-taint-sink client_upload $userId
     * @psalm-taint-sink client_upload $email
     * @psalm-taint-sink client_upload $subject
     * @psalm-taint-sink client_upload $body
     * @psalm-taint-sink client_upload $link
     */
    public function __construct(
        public string $userId,
        public string $email,
        public string $subject,
        public string $body,
        public string $link = '',
    ) {
    }
}
