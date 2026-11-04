<?php

declare(strict_types=1);

namespace App\Site\Job;

use AIC\Controller\Contracts\Job;
use AIC\Controller\Contracts\JobInput;
use AIC\Controller\Contracts\JobResponse;
use AIC\Controller\Domain\JobDone;
use AIC\Controller\Domain\JobFailed;
use AIC\Controller\Domain\JobRetry;
use Override;

/**
 * The demo's job (#46): with `mode=succeed` it asks for one retry and is done
 * on its second attempt; with `mode=fail` it asks to be tried again every
 * time, so the queue fails it after its last attempt; with `mode=broken` it
 * fails on its first attempt, straight to the failed record (#262).
 */
final readonly class DemoJob implements Job
{
    /**
     * @param JobInput $jobInput The payload, `mode`.
     *
     * @return JobResponse Done, a retry, or failed.
     */
    #[Override]
    public function run(JobInput $jobInput): JobResponse
    {
        if ($jobInput->value('mode', 'succeed') === 'broken') {
            return new JobFailed('The demo job was told to fail.');
        }

        return $jobInput->value('mode', 'succeed') === 'fail' || $jobInput->attempt < 2
            ? new JobRetry()
            : new JobDone();
    }
}
