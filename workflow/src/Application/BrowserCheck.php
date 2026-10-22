<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Workflow\Domain\CheckRecord;
use AIC\Workflow\Domain\CheckRow;
use AIC\Workflow\Domain\CheckState;
use AIC\Workflow\Domain\CheckStatus;
use AIC\Workflow\Domain\Fault;
use AIC\Workflow\Domain\Phase;
use AIC\Workflow\Domain\RunState;
use AIC\Workflow\Exception\WorkflowRefusal;
use Override;

/**
 * Browser evidence at check (#101): the agent's ticks on the spec's browser
 * items, and the ledger of browser tool calls at the phase. Until #102's
 * guard writes that ledger the calls read not measured, so the row is
 * recorded and never holds the phase.
 */
final readonly class BrowserCheck implements PhaseCheck
{
    /**
     * @param SpecFile         $specFile         The browser items.
     * @param EvidenceLog      $evidenceLog      The ledger.
     * @param EvidenceRecorder $evidenceRecorder Records the row.
     * @param RunSubject       $runSubject       The tree's subject hash.
     */
    public function __construct(
        private SpecFile $specFile,
        private EvidenceLog $evidenceLog,
        private EvidenceRecorder $evidenceRecorder,
        private RunSubject $runSubject,
    ) {
    }

    /**
     * @return Phase Check.
     */
    #[Override]
    public function phase(): Phase
    {
        return Phase::Check;
    }

    /**
     * @param RunState $runState The run, at check.
     *
     * @return CheckRow Passed when the work is not browser-testable; deferred, with the ticks and the calls, otherwise.
     */
    #[Override]
    public function check(RunState $runState): CheckRow
    {
        $text = $this->specFile->text($runState->runStart->specPath);
        $specContract = $text === null
            ? null
            : $this->specFile->contract($text);

        if (!$specContract instanceof SpecContract || !$specContract->browserTestable()) {
            return new CheckRow('browser', CheckStatus::Passed, 'the work is not browser-testable.', '');
        }

        try {
            $summary = $this->summary($specContract, $runState->runStart->runId);
        } catch (WorkflowRefusal $workflowRefusal) {
            return $this->evidenceRecorder->unrecorded('browser', $workflowRefusal);
        }

        $checkRecord = new CheckRecord('browser', CheckState::Skipped, Fault::None, $summary, $this->runSubject->hash($runState));

        return $this->evidenceRecorder->row($runState, Phase::Check, $checkRecord, '');
    }

    /**
     * @param SpecContract $specContract The spec.
     * @param string       $runId        The run.
     *
     * @throws WorkflowRefusal When the ledger cannot be read.
     *
     * @return string The ticks and the calls.
     */
    private function summary(SpecContract $specContract, string $runId): string
    {
        $calls = $this->evidenceLog->browserCalls($runId, Phase::Check);
        [$ticked, $items] = $specContract->browserTicks();
        $ledger = $calls === 0
            ? "browser tool calls not measured until #102's guard"
            : $calls . ' browser tool call(s) in the ledger';

        return $ticked . ' of ' . $items . ' browser item(s) ticked; ' . $ledger . '.';
    }
}
