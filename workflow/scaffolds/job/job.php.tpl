<?php

declare(strict_types=1);

namespace {namespace}\Job;

use AIC\Controller\Contracts\Job;
use AIC\Controller\Contracts\JobInput;
use AIC\Controller\Contracts\JobResponse;
use AIC\Controller\Domain\JobDone;
use Override;

/**
 * The `{name}` job, made by bin/aic make:job (#122): done at once.
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
