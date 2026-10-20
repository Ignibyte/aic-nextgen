<?php

declare(strict_types=1);

namespace AIC\Controller\Domain;

use AIC\Controller\Contracts\JobRecorder;
use AIC\Controller\Contracts\JobResponse;
use Override;

/**
 * A job that succeeded.
 */
final readonly class JobDone implements JobResponse
{
    /**
     * @param JobRecorder $jobRecorder Records the result.
     */
    #[Override]
    public function recordTo(JobRecorder $jobRecorder): void
    {
        $jobRecorder->done();
    }
}
