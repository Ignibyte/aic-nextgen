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
     */
    public function retry(int $delaySeconds): void;

    /**
     * @param string $reason Why it cannot succeed, for the failed record.
     */
    public function fail(string $reason): void;
}
