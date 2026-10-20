<?php

declare(strict_types=1);

namespace AIC\Controller\Contracts;

/**
 * What a Job returns. Sealed: only the result shapes in AIC\Controller\Domain
 * implement it (a phpat guard), and each records itself.
 */
interface JobResponse
{
    /**
     * @param JobRecorder $jobRecorder Records the result.
     */
    public function recordTo(JobRecorder $jobRecorder): void;
}
