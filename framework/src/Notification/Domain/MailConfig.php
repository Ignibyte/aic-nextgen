<?php

declare(strict_types=1);

namespace AIC\Notification\Domain;

/**
 * Where mail goes out (#48): an SMTP server and the sender's address, from
 * the `AIC_MAIL_*` environment (config/mail.php).
 */
final readonly class MailConfig
{
    /**
     * @param string $host The SMTP server.
     * @param int    $port Its port.
     * @param string $from The sender's address.
     */
    public function __construct(
        public string $host,
        public int $port,
        public string $from,
    ) {
    }
}
