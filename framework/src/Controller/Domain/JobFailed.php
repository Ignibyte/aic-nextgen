<?php

declare(strict_types=1);

namespace AIC\Controller\Domain;

use AIC\Controller\Contracts\JobRecorder;
use AIC\Controller\Contracts\JobResponse;
use Override;

/**
 * A job that cannot succeed: it goes to the failed record now.
 */
final readonly class JobFailed implements JobResponse
{
    /**
     * @param string $reason Why, for the failed record.
     */
    public function __construct(
        private string $reason,
    ) {
    }

    /**
     * @param JobRecorder $jobRecorder Records the result.
     */
    #[Override]
    public function recordTo(JobRecorder $jobRecorder): void
    {
        $jobRecorder->fail($this->reason);
    }
}
