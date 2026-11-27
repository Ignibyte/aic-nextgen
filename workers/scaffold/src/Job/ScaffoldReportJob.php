<?php

declare(strict_types=1);

namespace Worker\Scaffold\Job;

use AIC\Controller\Contracts\Job;
use AIC\Controller\Contracts\JobInput;
use AIC\Controller\Contracts\JobResponse;
use AIC\Controller\Domain\JobDone;
use Override;

/**
 * A job of the scaffold worker, made by bin/aic make:job --worker (#336):
 * done at once. It runs interpreted, never in the compiled site.
 */
final readonly class ScaffoldReportJob implements Job
{
    /**
     * @param JobInput $jobInput The payload.
     *
     * @return JobResponse Done.
     */
    #[Override]
    public function run(JobInput $jobInput): JobResponse
    {
        return new JobDone();
    }
}
