<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Workflow\Domain\CheckRecord;
use AIC\Workflow\Domain\FrozenSpec;
use AIC\Workflow\Domain\Guard\GuardRow;
use AIC\Workflow\Domain\Phase;
use AIC\Workflow\Exception\WorkflowRefusal;

/**
 * The evidence store (#101): every verdict the engine measured, with the
 * subject hash of the tree it judged, and what the run froze at plan. Only
 * the engine writes it.
 */
interface EvidenceLog
{
    /**
     * @param string     $runId      The run.
     * @param FrozenSpec $frozenSpec What planning ended on.
     *
     * @throws WorkflowRefusal When it cannot be written.
     */
    public function freeze(string $runId, FrozenSpec $frozenSpec): void;

    /**
     * @param string $runId The run.
     *
     * @throws WorkflowRefusal When the store cannot be read.
     *
     * @return FrozenSpec|null What it froze; null before planning ended.
     */
    public function frozen(string $runId): ?FrozenSpec;

    /**
     * @param string      $runId       The run.
     * @param Phase       $phase       The phase it was measured at.
     * @param int         $attempt     The attempt at that phase.
     * @param CheckRecord $checkRecord The verdict.
     *
     * @throws WorkflowRefusal When it cannot be written.
     */
    public function record(string $runId, Phase $phase, int $attempt, CheckRecord $checkRecord): void;

    /**
     * @param string $runId The run.
     * @param string $name  A check's name, such as `gate` or `gate:24`.
     *
     * @throws WorkflowRefusal When the store cannot be read.
     *
     * @return CheckRecord|null Its newest verdict in the run; null when it has none.
     */
    public function latest(string $runId, string $name): ?CheckRecord;

    /**
     * @param string $runId The run.
     * @param Phase  $phase A phase.
     *
     * @throws WorkflowRefusal When the store cannot be read.
     *
     * @return array<string, int> How many times each check failed at that phase, by name.
     */
    public function failures(string $runId, Phase $phase): array;

    /**
     * @param string $runId The run.
     *
     * @throws WorkflowRefusal When the store cannot be read.
     *
     * @return int How many of the guard's rows for it the store holds (#102).
     */
    public function toolCallCount(string $runId): int;

    /**
     * @param string         $runId The run.
     * @param list<GuardRow> $rows  The guard's rows for it the store does not hold yet, in order.
     *
     * @throws WorkflowRefusal When they cannot be written.
     */
    public function recordToolCalls(string $runId, array $rows): void;
}
