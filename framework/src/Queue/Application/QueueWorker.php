<?php

declare(strict_types=1);

namespace AIC\Queue\Application;

use AIC\Controller\Contracts\Job;
use AIC\Controller\Contracts\JobInput;
use AIC\Controller\Contracts\JobResponse;
use AIC\Controller\Domain\JobFailed;
use AIC\Controller\Domain\JobRetry;
use AIC\Database\Contracts\QueryException;
use AIC\Queue\Domain\ReservedJob;
use AIC\Session\Domain\StringMapJson;
use Throwable;

/**
 * Runs due jobs one at a time (#46), each from the registry by name; a job
 * that throws counts as a retry, and on its last attempt fails with the
 * message. A job name the registry lacks fails at once.
 */
final readonly class QueueWorker
{
    /**
     * @param PostgresQueue      $postgresQueue The queue.
     * @param array<string, Job> $jobs          The registry, by name.
     * @param StringMapJson      $stringMapJson Decodes payloads.
     */
    public function __construct(
        private PostgresQueue $postgresQueue,
        private array $jobs,
        private StringMapJson $stringMapJson = new StringMapJson(),
    ) {
    }

    /**
     * @param int $max The most jobs to run.
     *
     * @throws QueryException When the queue cannot be read or changed.
     *
     * @return int How many jobs ran.
     */
    public function work(int $max): int
    {
        $ran = 0;

        while ($ran < $max) {
            $reservedJob = $this->postgresQueue->reserve();

            if (!$reservedJob instanceof ReservedJob) {
                break;
            }

            $this->runOne($reservedJob);
            $ran += 1;
        }

        return $ran;
    }

    /**
     * @param ReservedJob $reservedJob A reserved job.
     *
     * @throws QueryException When the queue cannot be changed.
     */
    private function runOne(ReservedJob $reservedJob): void
    {
        $job = $this->jobs[$reservedJob->job] ?? null;

        if (!$job instanceof Job) {
            $this->postgresQueue->bury($reservedJob, 'No job is registered as "' . $reservedJob->job . '".');

            return;
        }

        $this->outcome($job, $reservedJob)->recordTo(new ReservedJobRecorder($this->postgresQueue, $reservedJob));
    }

    /**
     * @param Job         $job         The job.
     * @param ReservedJob $reservedJob Its reservation.
     *
     * @return JobResponse What it returned; a retry when it threw, or a failure on its last attempt.
     */
    private function outcome(Job $job, ReservedJob $reservedJob): JobResponse
    {
        try {
            return $job->run(new JobInput($this->stringMapJson->decode($reservedJob->payload), $reservedJob->attempts));
        } catch (Throwable $throwable) {
            return $reservedJob->attempts >= JobRetry::ATTEMPTS
                ? new JobFailed($throwable->getMessage())
                : new JobRetry();
        }
    }
}
