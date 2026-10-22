<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Workflow\Domain\SeekerInspection;
use AIC\Workflow\Exception\WorkflowRefusal;

/**
 * Where filed ledgers are kept (#103): the evidence store, written only by
 * the engine.
 */
interface SeekerLog
{
    /**
     * @param string           $runId            The run.
     * @param SeekerInspection $seekerInspection The filed ledger, its findings with it, in one transaction.
     *
     * @throws WorkflowRefusal When it cannot be written.
     */
    public function file(string $runId, SeekerInspection $seekerInspection): void;

    /**
     * @param string $runId The run.
     *
     * @throws WorkflowRefusal When the store cannot be read.
     *
     * @return SeekerInspection|null The newest inspection that counts; null when none does.
     */
    public function latestCounted(string $runId): ?SeekerInspection;

    /**
     * @param string $runId The run.
     *
     * @throws WorkflowRefusal When the store cannot be read.
     *
     * @return string When the newest ledger was filed, counted or not; empty before the first.
     */
    public function lastFiledAt(string $runId): string;

    /**
     * @param string $runId The run.
     *
     * @throws WorkflowRefusal When the store cannot be read.
     *
     * @return list<string> The digest of every ledger filed for the run.
     */
    public function filedDigests(string $runId): array;

    /**
     * @param string $runId The run.
     *
     * @throws WorkflowRefusal When the store cannot be read.
     *
     * @return int The next round's number, from 1.
     */
    public function nextRound(string $runId): int;
}
