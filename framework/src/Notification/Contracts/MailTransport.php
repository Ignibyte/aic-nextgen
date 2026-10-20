<?php

declare(strict_types=1);

namespace AIC\Notification\Contracts;

use RuntimeException;

/**
 * Hands a finished message to a mail server (#48).
 */
interface MailTransport
{
    /**
     * @param string $recipient The recipient's address.
     * @param string $message   The message, as MailMessage writes it.
     *
     * @throws RuntimeException When the server cannot take it now.
     */
    public function send(string $recipient, string $message): void;
}
