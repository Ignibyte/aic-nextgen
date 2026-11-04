<?php

declare(strict_types=1);

namespace AIC\Queue\Domain;

/**
 * One job in the queue, as an app shows it (#262). Times are ISO 8601 in UTC,
 * as in `2026-11-04T12:00:00Z`.
 */
final readonly class QueuedJob
{
    /**
     * @param int        $id         Its row.
     * @param string     $job        The job's name in the registry.
     * @param string     $payload    Its payload, a JSON object of strings.
     * @param QueueState $state      Waiting, running or retrying.
     * @param int        $attempts   The attempts so far.
     * @param string     $dueAt      When it is due, the next attempt's time for a retrying job.
     * @param string     $reservedAt When a worker last took it; empty when none has.
     */
    public function __construct(
        public int $id,
        public string $job,
        public string $payload,
        public QueueState $state,
        public int $attempts,
        public string $dueAt,
        public string $reservedAt,
    ) {
    }
}
