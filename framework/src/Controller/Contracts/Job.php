<?php

declare(strict_types=1);

namespace AIC\Controller\Contracts;

/**
 * The Job kind (#46): queued or scheduled work, run by the queue worker.
 */
interface Job
{
    /**
     * @param JobInput $jobInput The payload and the attempt.
     *
     * @return JobResponse Done, retry or fail.
     */
    public function run(JobInput $jobInput): JobResponse;
}
