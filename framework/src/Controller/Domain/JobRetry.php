<?php

declare(strict_types=1);

namespace AIC\Controller\Domain;

use AIC\Controller\Contracts\JobRecorder;
use AIC\Controller\Contracts\JobResponse;
use InvalidArgumentException;
use Override;

/**
 * A job to try again: after at least its delay, and only while attempts are
 * left; then it fails.
 */
final readonly class JobRetry implements JobResponse
{
    /**
     * @param int $delaySeconds The least time to wait; the queue's backoff may wait longer.
     *
     * @throws InvalidArgumentException When the delay is negative.
     */
    public function __construct(
        private int $delaySeconds = 0,
    ) {
        if ($delaySeconds < 0) {
            throw new InvalidArgumentException('JobRetry: a delay is 0 or more seconds.');
        }
    }

    /**
     * @param JobRecorder $jobRecorder Records the result.
     */
    #[Override]
    public function recordTo(JobRecorder $jobRecorder): void
    {
        $jobRecorder->retry($this->delaySeconds);
    }
}
