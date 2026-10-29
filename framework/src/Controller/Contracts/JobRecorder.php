<?php

declare(strict_types=1);

namespace AIC\Controller\Contracts;

/**
 * Where a Job's result goes (#46): the queue worker records it against the
 * job it ran.
 */
interface JobRecorder
{
    /**
     * The job is done; it leaves the queue.
     */
    public function done(): void;

    /**
     * @param int $delaySeconds The least time to wait before the next attempt.
     * @param int $attempts     How many attempts the job gets in all (#208); when this one reached it, the job fails.
     */
    public function retry(int $delaySeconds, int $attempts): void;

    /**
     * @param string $reason Why it cannot succeed, for the failed record.
     */
    public function fail(string $reason): void;
}
