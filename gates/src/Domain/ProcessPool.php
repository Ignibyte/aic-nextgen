<?php

declare(strict_types=1);

namespace Gates\Domain;

/**
 * Seam for running many child processes side by side, a capped number at a time.
 *
 * The parallel sibling of `ProcessRunner`: the same capture rules (stdout and
 * stderr kept apart, no `exit()`, the parent's working directory untouched),
 * applied to a list of jobs. The default implementation is
 * `Gates\Infrastructure\ProcProcessPool`.
 */
interface ProcessPool
{
    /**
     * Run every job and return its captured result.
     *
     * Implementations MUST return one result per job, in the order the jobs
     * were given, whatever order they finish in.
     *
     * @param list<ProcessJob> $jobs The commands to run.
     *
     * @return list<ProcessResult> One result per job, in job order.
     */
    public function run(array $jobs): array;
}
