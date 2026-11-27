<?php

declare(strict_types=1);

namespace {namespace}\Job;

use AIC\Controller\Contracts\Job;
use AIC\Controller\Contracts\JobInput;
use AIC\Controller\Contracts\JobResponse;
use AIC\Controller\Domain\JobDone;
use Override;

/**
 * A job of the {worker} worker, made by bin/aic make:job --worker (#336):
 * done at once. It runs interpreted, never in the compiled site.
 */
final readonly class {class} implements Job
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
