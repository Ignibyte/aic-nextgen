<?php

declare(strict_types=1);

namespace AIC\Notification\Application;

use AIC\Controller\Contracts\Job;
use AIC\Controller\Contracts\JobInput;
use AIC\Controller\Contracts\JobResponse;
use AIC\Controller\Domain\JobDone;
use AIC\Controller\Domain\JobFailed;
use AIC\Notification\Contracts\Channel;
use AIC\Notification\Contracts\Notification;
use Override;
use RuntimeException;

/**
 * Delivers one notification through one channel (#48), the job Notifier
 * queues. A channel the job does not know fails it; a delivery that throws
 * is retried by the queue.
 */
final readonly class DeliverNotification implements Job
{
    /**
     * @param array<string, Channel> $channels The channels by name, such as `mail` and `database`.
     */
    public function __construct(
        private array $channels,
    ) {
    }

    /**
     * @param JobInput $jobInput The channel and the notification.
     *
     * @throws RuntimeException When the channel cannot deliver now.
     *
     * @return JobResponse Done, or failed for an unknown channel.
     */
    #[Override]
    public function run(JobInput $jobInput): JobResponse
    {
        $name = $jobInput->value('channel', '');
        $channel = $this->channels[$name] ?? null;

        if (!$channel instanceof Channel) {
            return new JobFailed('No notification channel is named "' . $name . '".');
        }

        return $this->deliver($channel, $jobInput);
    }

    /**
     * @param Channel  $channel  The channel.
     * @param JobInput $jobInput The notification.
     *
     * @throws RuntimeException When the channel cannot deliver now.
     *
     * @return JobResponse Done.
     */
    private function deliver(Channel $channel, JobInput $jobInput): JobResponse
    {
        $channel->deliver(new Notification(
            $jobInput->value('user', ''),
            $jobInput->value('email', ''),
            $jobInput->value('subject', ''),
            $jobInput->value('body', ''),
        ));

        return new JobDone();
    }
}
