<?php

declare(strict_types=1);

namespace AIC\Queue\Domain;

/**
 * One job in the failed record (#262), which `queue:retry` or
 * `PostgresQueue::retry()` queues again and `PostgresQueue::discard()` drops.
 */
final readonly class FailedJob
{
    /**
     * @param int    $id       Its row in the failed record.
     * @param string $job      The job's name in the registry.
     * @param string $payload  Its payload, a JSON object of strings.
     * @param int    $attempts The attempts it had.
     * @param string $reason   Why it failed.
     * @param string $failedAt When it failed, ISO 8601 in UTC.
     */
    public function __construct(
        public int $id,
        public string $job,
        public string $payload,
        public int $attempts,
        public string $reason,
        public string $failedAt,
    ) {
    }
}
