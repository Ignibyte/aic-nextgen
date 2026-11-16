<?php

declare(strict_types=1);

namespace {namespace}\Job;

use AIC\Controller\Contracts\Job;
use AIC\Controller\Contracts\JobInput;
use AIC\Controller\Contracts\JobResponse;
use AIC\Controller\Domain\JobDone;
use AIC\Controller\Domain\JobRetry;
use {namespace}\Service\{name}Poller;
use Override;

/**
 * The `{name|kebab}-poll` job, made by bin/aic make poller (#298): on its
 * schedule it asks the poller for what changed upstream, and tries again in a
 * minute when the upstream does not answer.
 */
final readonly class {name}Poll implements Job
{
    /**
     * @param {name}Poller ${name|camel}Poller Reads the upstream system.
     */
    public function __construct(
        private {name}Poller ${name|camel}Poller,
    ) {
    }

    /**
     * @param JobInput $jobInput No payload.
     *
     * @return JobResponse Done, or a retry in a minute.
     */
    #[Override]
    public function run(JobInput $jobInput): JobResponse
    {
        return $this->{name|camel}Poller->poll()
            ? new JobDone()
            : new JobRetry(60);
    }
}
