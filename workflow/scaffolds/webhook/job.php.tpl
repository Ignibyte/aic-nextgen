<?php

declare(strict_types=1);

namespace {namespace}\Job;

use AIC\Controller\Contracts\Job;
use AIC\Controller\Contracts\JobInput;
use AIC\Controller\Contracts\JobResponse;
use AIC\Controller\Domain\JobDone;
use AIC\Controller\Domain\JobFailed;
use {namespace}\Service\{payload};
use Override;

/**
 * The job `{job}`, made by bin/aic make:webhook (#321): one
 * delivery to {path}, queued by {webhook}. An empty body
 * fails; the work on the delivery goes here.
 */
final readonly class {class} implements Job
{
    /**
     * @param JobInput $jobInput The payload: the delivery's body.
     *
     * @return JobResponse Done, or failed on an empty body.
     */
    #[Override]
    public function run(JobInput $jobInput): JobResponse
    {
        ${variable} = new {payload}($jobInput->value('body', ''));

        return ${variable}->body === ''
            ? new JobFailed('The delivery had no body.')
            : new JobDone();
    }
}
