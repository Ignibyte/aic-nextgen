<?php

declare(strict_types=1);

namespace App\Site\Service;

use AIC\Database\Contracts\QueryException;
use AIC\Queue\Application\QueueRecords;

/**
 * The jobs page's queue lines (#262): each job in the queue with its id,
 * state, attempts, due time and last reservation, and each failed job with its reason, read through
 * AIC's queue records.
 */
final readonly class QueueLines
{
    /**
     * @param QueueRecords $queueRecords The queue and the failed record.
     */
    public function __construct(
        private QueueRecords $queueRecords,
    ) {
    }

    /**
     * @throws QueryException When the queue cannot be read.
     *
     * @return list<string> One line per job in the queue, newest first.
     */
    public function queued(): array
    {
        $lines = [];

        foreach ($this->queueRecords->queued() as $queuedJob) {
            $reserved = $queuedJob->reservedAt === ''
                ? ''
                : ', reserved ' . $queuedJob->reservedAt;
            $lines[] = '#' . $queuedJob->id . ' ' . $queuedJob->job . ' ' . $queuedJob->payload . ' ('
                . $queuedJob->state->value . '), attempts ' . $queuedJob->attempts . ', due ' . $queuedJob->dueAt . $reserved;
        }

        return $lines;
    }

    /**
     * @throws QueryException When the record cannot be read.
     *
     * @return array<int, string> One line per failed job, newest first, by its id.
     */
    public function failed(): array
    {
        $lines = [];

        foreach ($this->queueRecords->failed() as $failedJob) {
            $lines[$failedJob->id] = '#' . $failedJob->id . ' ' . $failedJob->job . ' after ' . $failedJob->attempts
                . ' attempts: ' . $failedJob->reason . ', failed ' . $failedJob->failedAt;
        }

        return $lines;
    }
}
