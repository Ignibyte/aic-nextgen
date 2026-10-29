<?php

declare(strict_types=1);

namespace AIC\Notification\Application;

use AIC\Controller\Contracts\Job;
use AIC\Controller\Contracts\JobInput;
use AIC\Controller\Contracts\JobResponse;
use AIC\Controller\Domain\JobDone;
use AIC\Controller\Domain\JobFailed;
use AIC\Controller\Domain\JobRetry;
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
     * The attempts a delivery gets when its channel cannot deliver now (#208): a mail server that is down.
     */
    private const int ATTEMPTS = 5;

    /**
     * The least wait before the next attempt, in seconds; the queue's backoff may wait longer.
     */
    private const int DELAY = 30;

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
     * @return JobResponse Done; failed for an unknown channel; a delayed retry when the channel cannot deliver now,
     *                     failed with the reason on the last attempt (#208).
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
     * @return JobResponse Done, a delayed retry, or failed with the channel's reason on the last attempt.
     */
    private function deliver(Channel $channel, JobInput $jobInput): JobResponse
    {
        try {
            $channel->deliver(new Notification(
                $jobInput->value('user', ''),
                $jobInput->value('email', ''),
                $jobInput->value('subject', ''),
                $jobInput->value('body', ''),
                $jobInput->value('link', ''),
            ));
        } catch (RuntimeException $runtimeException) {
            return $jobInput->attempt >= self::ATTEMPTS
                ? new JobFailed($runtimeException->getMessage())
                : new JobRetry(self::DELAY, self::ATTEMPTS);
        }

        return new JobDone();
    }
}
