<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Workflow\Domain\CheckRecord;
use AIC\Workflow\Domain\CheckRow;
use AIC\Workflow\Domain\CheckStatus;
use AIC\Workflow\Domain\Phase;
use AIC\Workflow\Domain\RunState;
use AIC\Workflow\Exception\WorkflowRefusal;

/**
 * Records a check's verdict and gives its report row (#101, Droost's
 * `EvidenceRecorder`). Unlike Droost's, a write that fails holds the phase:
 * a verdict that was never recorded cannot be shown fresh later.
 */
final readonly class EvidenceRecorder
{
    /**
     * @param EvidenceLog $evidenceLog The store.
     */
    public function __construct(
        private EvidenceLog $evidenceLog,
    ) {
    }

    /**
     * @param RunState    $runState    The run.
     * @param Phase       $phase       The phase the check ran at.
     * @param CheckRecord $checkRecord Its verdict.
     * @param string      $remedy      What to do when it holds the phase.
     *
     * @return CheckRow Its row; failed when the verdict could not be recorded.
     */
    public function row(RunState $runState, Phase $phase, CheckRecord $checkRecord, string $remedy): CheckRow
    {
        try {
            $this->evidenceLog->record($runState->runStart->runId, $phase, $runState->attempt($phase) + 1, $checkRecord);
        } catch (WorkflowRefusal $workflowRefusal) {
            return $this->unrecorded($checkRecord->name, $workflowRefusal);
        }

        $checkStatus = $checkRecord->checkState->checkStatus();

        return new CheckRow($checkRecord->name, $checkStatus, $checkRecord->summary, $checkStatus->holds() ? $remedy : '');
    }

    /**
     * @param string          $check           The check.
     * @param WorkflowRefusal $workflowRefusal Why its verdict was not recorded.
     *
     * @return CheckRow A failed row saying so.
     */
    public function unrecorded(string $check, WorkflowRefusal $workflowRefusal): CheckRow
    {
        return new CheckRow(
            $check,
            CheckStatus::Held,
            'its verdict could not be recorded: ' . $workflowRefusal->getMessage(),
            'the operator makes var/workflow/evidence.sqlite writable, then runs bin/aic workflow:run.',
        );
    }
}
