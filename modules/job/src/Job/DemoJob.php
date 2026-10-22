<?php

declare(strict_types=1);

namespace App\Job\Job;

use AIC\Controller\Contracts\Job;
use AIC\Controller\Contracts\JobInput;
use AIC\Controller\Contracts\JobResponse;
use AIC\Controller\Domain\JobDone;
use AIC\Controller\Domain\JobRetry;
use Override;

/**
 * The demo's job (#46): with `mode=succeed` it asks for one retry and is done
 * on its second attempt; with `mode=fail` it asks to be tried again every
 * time, so the queue fails it after its last attempt.
 */
final readonly class DemoJob implements Job
{
    /**
     * @param JobInput $jobInput The payload, `mode`.
     *
     * @return JobResponse Done, or a retry.
     */
    #[Override]
    public function run(JobInput $jobInput): JobResponse
    {
        return $jobInput->value('mode', 'succeed') === 'fail' || $jobInput->attempt < 2
            ? new JobRetry()
            : new JobDone();
    }
}
