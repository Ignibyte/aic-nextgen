<?php

declare(strict_types=1);

namespace AIC\Notification\Application;

use AIC\Notification\Contracts\Channel;
use AIC\Notification\Contracts\MailTransport;
use AIC\Notification\Contracts\Notification;
use AIC\Notification\Domain\MailConfig;
use AIC\Notification\Domain\MailMessage;
use InvalidArgumentException;
use Override;
use RuntimeException;

use function time;

/**
 * Mails a notification to its user's address (#48).
 */
final readonly class MailChannel implements Channel
{
    /**
     * @param MailTransport $mailTransport Sends the message.
     * @param MailConfig    $mailConfig    The sender's address.
     */
    public function __construct(
        private MailTransport $mailTransport,
        private MailConfig $mailConfig,
    ) {
    }

    /**
     * @param Notification $notification What to mail.
     *
     * @throws InvalidArgumentException When an address or the subject is malformed.
     * @throws RuntimeException         When the server cannot take it now.
     */
    #[Override]
    public function deliver(Notification $notification): void
    {
        $mailMessage = new MailMessage(
            $this->mailConfig->from,
            $notification->email,
            $notification->subject,
            $notification->body,
            time(),
        );
        $this->mailTransport->send($notification->email, $mailMessage->text());
    }
}
