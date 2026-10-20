<?php

declare(strict_types=1);

namespace AIC\Notification\Application;

use AIC\Database\Contracts\QueryException;
use AIC\Notification\Contracts\Notification;
use AIC\Queue\Application\PostgresQueue;
use JsonException;

/**
 * Sends notifications (#48): one queued `notify` job per channel, so each
 * channel delivers, retries and fails on its own.
 */
final readonly class Notifier
{
    /**
     * The job that delivers, as the app's registry names it.
     */
    public const string JOB = 'notify';

    /**
     * @param PostgresQueue $postgresQueue The queue.
     */
    public function __construct(
        private PostgresQueue $postgresQueue,
    ) {
    }

    /**
     * @param Notification $notification What to send.
     * @param list<string> $channels     The channels, by the names DeliverNotification knows.
     *
     * @throws JsonException  When a payload cannot be encoded.
     * @throws QueryException When a job cannot be queued.
     */
    public function send(Notification $notification, array $channels): void
    {
        foreach ($channels as $channel) {
            $this->postgresQueue->dispatch(self::JOB, [
                'body' => $notification->body,
                'channel' => $channel,
                'email' => $notification->email,
                'subject' => $notification->subject,
                'user' => $notification->userId,
            ], 0);
        }
    }
}
