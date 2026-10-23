<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Workflow\Domain\CheckRow;
use AIC\Workflow\Domain\CheckStatus;
use AIC\Workflow\Domain\Findings;
use AIC\Workflow\Domain\Phase;
use AIC\Workflow\Domain\RunState;
use AIC\Workflow\Exception\WorkflowRefusal;
use Override;

/**
 * Browser evidence at check (#101, #102): when the spec says
 * `browser_testable: Yes`, the check holds until every browser item is
 * ticked and the guard's ledger holds at least one browser tool call it let
 * through at check. Work that is not browser-testable passes.
 */
final readonly class BrowserCheck implements PhaseCheck
{
    /**
     * What to do when the browser evidence falls short.
     */
    private const string REMEDY = 'open each page in a browser on this box, do the action, look, and tick its item with what you saw.';

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
     * @throws WorkflowRefusal When a called workflow operation refuses its input or environment.
     *
     * @return CheckRow Passed when the work is not browser-testable, or its evidence is there; failed otherwise.
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
            $failures = $this->failures($specContract, $runState->runStart->runId);
        } catch (WorkflowRefusal $workflowRefusal) {
            return $this->evidenceRecorder->unrecorded('browser', $workflowRefusal);
        }

        $passed = 'every browser item is ticked, and the ledger holds the browser calls.';
        $findings = new Findings('browser', $failures, $passed, $this->runSubject->hash($runState));

        return $this->evidenceRecorder->row($runState, Phase::Check, $findings->record(), self::REMEDY);
    }

    /**
     * @param SpecContract $specContract The spec, browser-testable.
     * @param string       $runId        The run.
     *
     * @throws WorkflowRefusal When the ledger cannot be read.
     *
     * @return list<string> What the browser evidence lacks: a browser call in the ledger, an unticked item.
     */
    private function failures(SpecContract $specContract, string $runId): array
    {
        $calls = $this->evidenceLog->browserCalls($runId, Phase::Check);
        [$ticked, $items] = $specContract->browserTicks();
        $failures = $calls === 0
            ? ['the guard recorded no browser tool call at check.']
            : [];

        return $ticked < $items
            ? [...$failures, $ticked . ' of ' . $items . ' browser item(s) ticked.']
            : $failures;
    }
}
