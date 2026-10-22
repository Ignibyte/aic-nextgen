<?php

declare(strict_types=1);

namespace AIC\Module\Application;

use AIC\Controller\Contracts\Job;
use AIC\Database\Contracts\Database;
use AIC\Notification\Application\DatabaseChannel;
use AIC\Notification\Application\DeliverNotification;
use AIC\Notification\Application\MailChannel;
use AIC\Notification\Application\Notifier;
use AIC\Notification\Contracts\MailTransport;
use AIC\Notification\Domain\MailConfig;

/**
 * The framework's own queue jobs (#48, placed by #93): `notify`, which delivers
 * one notification through the database or the mail channel.
 */
final readonly class FrameworkJobs
{
    /**
     * @param Database      $database      Holds the database notifications.
     * @param MailTransport $mailTransport Sends the mail notifications.
     * @param MailConfig    $mailConfig    The mail settings.
     */
    public function __construct(
        private Database $database,
        private MailTransport $mailTransport,
        private MailConfig $mailConfig,
    ) {
    }

    /**
     * @return array<string, Job> The framework's jobs by name.
     */
    public function byName(): array
    {
        $channels = [
            'database' => new DatabaseChannel($this->database),
            'mail' => new MailChannel($this->mailTransport, $this->mailConfig),
        ];

        return [Notifier::JOB => new DeliverNotification($channels)];
    }
}
