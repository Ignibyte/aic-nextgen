<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

/**
 * The guard's record of the subagents that returned (#103 over #102's
 * ledger): whether the hook that records them is wired, and the digests of
 * what each `aic-seeker` returned.
 */
interface SeekerReturns
{
    /**
     * @return bool Whether the guard records subagent returns in this project.
     */
    public function wired(): bool;

    /**
     * @param string $runId The run.
     * @param string $since A UTC time; empty for the run's start.
     *
     * @return list<string> The digest of each ledger an `aic-seeker` returned at or after it.
     */
    public function digests(string $runId, string $since): array;
}
