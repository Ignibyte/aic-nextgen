<?php

declare(strict_types=1);

namespace AIC\Controller\Domain;

use AIC\Controller\Contracts\JobRecorder;
use AIC\Controller\Contracts\JobResponse;
use InvalidArgumentException;
use Override;

/**
 * A job to try again: after at least its delay, and only while attempts are
 * left (three unless the job allows more, #208); then it fails.
 */
final readonly class JobRetry implements JobResponse
{
    /**
     * How many attempts a job gets in all unless it says otherwise.
     */
    public const int ATTEMPTS = 3;

    /**
     * @param int $delaySeconds The least time to wait; the queue's backoff may wait longer.
     * @param int $attempts     How many attempts the job gets in all (#208); the attempt that reaches it fails.
     *
     * @throws InvalidArgumentException When the delay is negative or the attempts below one.
     */
    public function __construct(
        private int $delaySeconds = 0,
        private int $attempts = self::ATTEMPTS,
    ) {
        if ($delaySeconds < 0) {
            throw new InvalidArgumentException('JobRetry: a delay is 0 or more seconds.');
        }

        if ($attempts < 1) {
            throw new InvalidArgumentException('JobRetry: a job gets at least one attempt.');
        }
    }

    /**
     * @param JobRecorder $jobRecorder Records the result.
     */
    #[Override]
    public function recordTo(JobRecorder $jobRecorder): void
    {
        $jobRecorder->retry($this->delaySeconds, $this->attempts);
    }
}
