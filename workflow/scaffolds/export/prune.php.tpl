<?php

declare(strict_types=1);

namespace {namespace}\Job;

use AIC\Controller\Contracts\Job;
use AIC\Controller\Contracts\JobInput;
use AIC\Controller\Contracts\JobResponse;
use AIC\Controller\Domain\JobDone;
use AIC\Database\Contracts\QueryException;
use AIC\Entity\Application\EntityNotFoundException;
use AIC\Storage\Application\PrivateFiles;
use {namespace}\Repository\{repository};
use InvalidArgumentException;
use Override;

/**
 * The job `{prune}`, made by bin/aic make:export (#323), queued by its
 * schedule each day at 04:15 UTC: deletes each export past its {days}
 * days, its file and then its row. A younger one stays.
 */
final readonly class {class} implements Job
{
    /**
{params}     */
    public function __construct(
        private {repository} ${repositoryVariable},
        private PrivateFiles $privateFiles,
    ) {
    }

    /**
     * @param JobInput $jobInput Nothing: the payload is empty.
     *
     * @throws InvalidArgumentException When a type or a key is invalid.
     * @throws EntityNotFoundException  When an export is gone before its delete; the queue retries.
     * @throws QueryException           When the exports cannot be read or one deleted; the queue retries.
     *
     * @return JobResponse Done.
     */
    #[Override]
    public function run(JobInput $jobInput): JobResponse
    {
        foreach ($this->{repositoryVariable}->expired() as $id) {
            $this->privateFiles->delete($this->{repositoryVariable}->key($id));
            $this->{repositoryVariable}->delete($id);
        }

        return new JobDone();
    }
}
