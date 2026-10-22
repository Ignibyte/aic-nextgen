<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Workflow\Domain\CheckRecord;
use AIC\Workflow\Domain\GateResult;
use AIC\Workflow\Domain\GateRun;
use AIC\Workflow\Domain\Phase;
use AIC\Workflow\Domain\RunState;
use AIC\Workflow\Exception\WorkflowRefusal;

/**
 * Runs the project's gate suite and records what it found (#101, Droost's
 * `GateRunner` trimmed to one command): one row per `gate:N` and one for the
 * whole run, `gate`, each with the subject hash of the tree it judged.
 */
final readonly class GateRunner
{
    /**
     * @param GateExecutor $gateExecutor Runs `composer run gate`.
     * @param GateSummary  $gateSummary  Reads it.
     * @param EvidenceLog  $evidenceLog  Keeps the verdicts.
     */
    public function __construct(
        private GateExecutor $gateExecutor,
        private GateSummary $gateSummary,
        private EvidenceLog $evidenceLog,
    ) {
    }

    /**
     * @param RunState     $runState    The run.
     * @param Phase        $phase       The phase it runs at.
     * @param list<string> $gates       The gate names frozen at plan.
     * @param string       $subjectHash The tree it judges.
     *
     * @throws WorkflowRefusal When a verdict cannot be recorded.
     *
     * @return GateRun What it found.
     */
    public function run(RunState $runState, Phase $phase, array $gates, string $subjectHash): GateRun
    {
        $gateRun = $this->gateSummary->read($this->gateExecutor->run(), $gates);
        $runId = $runState->runStart->runId;
        $attempt = $runState->attempt($phase) + 1;
        $gateStatus = $gateRun->gateStatus;

        foreach ($gateRun->results as $result) {
            $this->evidenceLog->record($runId, $phase, $attempt, $this->record($result, $subjectHash));
        }

        $checkRecord = new CheckRecord('gate', $gateStatus->checkState(), $gateStatus->fault(), $gateRun->summary, $subjectHash);
        $this->evidenceLog->record($runId, $phase, $attempt, $checkRecord);

        return $gateRun;
    }

    /**
     * @param GateResult $gateResult  One gate's result.
     * @param string     $subjectHash The tree.
     *
     * @return CheckRecord Its verdict, with its output.
     */
    private function record(GateResult $gateResult, string $subjectHash): CheckRecord
    {
        $gateStatus = $gateResult->gateStatus;

        return new CheckRecord(
            $gateResult->name,
            $gateStatus->checkState(),
            $gateStatus->fault(),
            $gateResult->summary,
            $subjectHash,
            $gateResult->transcript,
        );
    }
}
