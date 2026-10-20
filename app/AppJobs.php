<?php

declare(strict_types=1);

namespace App;

use AIC\Controller\Contracts\Job;
use AIC\Database\Contracts\Database;
use AIC\Notification\Application\DatabaseChannel;
use AIC\Notification\Application\DeliverNotification;
use AIC\Notification\Application\MailChannel;
use AIC\Notification\Application\Notifier;
use AIC\Notification\Contracts\MailTransport;
use AIC\Notification\Domain\MailConfig;
use App\Job\DemoJob;

/**
 * The app's jobs by name (#46): the queue worker's registry, and the list
 * build/surface.json shows. A job is queued by its name, never its class.
 */
final readonly class AppJobs
{
    /**
     * @param Database      $database      The database, for the notification channel.
     * @param MailTransport $mailTransport Sends mail.
     * @param MailConfig    $mailConfig    The sender's address.
     */
    public function __construct(
        private Database $database,
        private MailTransport $mailTransport,
        private MailConfig $mailConfig,
    ) {
    }

    /**
     * @return array<string, Job> The jobs by name.
     */
    public function byName(): array
    {
        $channels = [
            'database' => new DatabaseChannel($this->database),
            'mail' => new MailChannel($this->mailTransport, $this->mailConfig),
        ];

        return ['demo' => new DemoJob(), Notifier::JOB => new DeliverNotification($channels)];
    }
}
