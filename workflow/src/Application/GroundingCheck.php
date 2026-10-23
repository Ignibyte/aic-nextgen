<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Workflow\Domain\CheckRecord;
use AIC\Workflow\Domain\CheckRow;
use AIC\Workflow\Domain\CheckState;
use AIC\Workflow\Domain\CheckStatus;
use AIC\Workflow\Domain\CodeQuery;
use AIC\Workflow\Domain\Fault;
use AIC\Workflow\Domain\Phase;
use AIC\Workflow\Domain\RunState;
use AIC\Workflow\Exception\WorkflowRefusal;
use Override;

use function count;
use function implode;
use function sprintf;

/**
 * Plan's grounding (#118), after the contract: on a code index it rebuilds
 * in full each attempt, every File Manifest row and Entry Points class must
 * be real, and this run's own lookups at plan must show the agent outlined
 * the code, looked up what it modifies and names, and searched before
 * creating a class. Every reason is reported at once. A miss is the agent's
 * to clear and spends no retry budget; an index or log the engine cannot
 * read holds the run for the operator.
 */
final readonly class GroundingCheck implements PhaseCheck
{
    /**
     * What the agent does about a miss.
     */
    private const string REMEDY = 'run the lookups each reason names, fix the rows it shows wrong, then run bin/aic workflow:run again.';

    /**
     * A miss's summary: the lookups so far, so a new lookup is progress the stall rule sees (#117), then every reason.
     */
    private const string MISSED = 'plan has not looked yet (%d lookups so far): %s';

    /**
     * What the operator does when the index or the log cannot be read.
     */
    private const string HELD = 'the operator fixes what bin/aic code:index reports, then runs bin/aic workflow:run from a terminal.';

    /**
     * @param GroundingSpec    $groundingSpec    Reads the spec against the index and the lookups.
     * @param CodeIndex        $codeIndex        Rebuilt in full each attempt, so no row written in by hand counts.
     * @param CodeQueryLedger  $codeQueryLedger  This run's lookups.
     * @param EvidenceRecorder $evidenceRecorder Records the row.
     * @param RunSubject       $runSubject       The tree's subject hash.
     */
    public function __construct(
        private GroundingSpec $groundingSpec,
        private CodeIndex $codeIndex,
        private CodeQueryLedger $codeQueryLedger,
        private EvidenceRecorder $evidenceRecorder,
        private RunSubject $runSubject,
    ) {
    }

    /**
     * @return Phase Plan.
     */
    #[Override]
    public function phase(): Phase
    {
        return Phase::Plan;
    }

    /**
     * @param RunState $runState The run, at plan.
     *
     * @throws WorkflowRefusal When a called workflow operation refuses its input or environment.
     *
     * @return CheckRow Passed when the plan is grounded; failed naming every reason; held when the engine cannot look.
     */
    #[Override]
    public function check(RunState $runState): CheckRow
    {
        try {
            $this->codeIndex->refresh(true);
            $codeQueries = $this->codeQueryLedger->queries($runState->runStart->runId, Phase::Plan);
        } catch (WorkflowRefusal $workflowRefusal) {
            return $this->held($runState, $workflowRefusal->getMessage());
        }

        $reasons = $this->groundingSpec->reasons($runState, $codeQueries);
        $hash = $this->runSubject->hash($runState);
        $checkRecord = $reasons === []
            ? new CheckRecord('grounding', CheckState::Satisfied, Fault::None, $this->groundingSpec
                ->summary($runState, $codeQueries), $hash)
            : new CheckRecord('grounding', CheckState::Blocked, Fault::Agent, $this->missed($codeQueries, $reasons), $hash);

        return $this->evidenceRecorder->row($runState, Phase::Plan, $checkRecord, self::REMEDY);
    }

    /**
     * @param list<CodeQuery> $codeQueries This run's lookups at plan.
     * @param list<string>    $reasons     Every reason the plan is not grounded.
     *
     * @return string The miss's summary.
     */
    private function missed(array $codeQueries, array $reasons): string
    {
        return sprintf(self::MISSED, count($codeQueries), implode(' ', $reasons));
    }

    /**
     * @param RunState $runState The run.
     * @param string   $why      Why the index or the log could not be read.
     *
     * @return CheckRow Held for the operator, recorded as an environment error.
     */
    private function held(RunState $runState, string $why): CheckRow
    {
        $summary = 'the engine could not look: ' . $why;
        $checkRecord = new CheckRecord('grounding', CheckState::Errored, Fault::Environment, $summary, '');
        $this->evidenceRecorder->row($runState, Phase::Plan, $checkRecord, self::HELD);

        return new CheckRow('grounding', CheckStatus::Held, $summary, self::HELD);
    }
}
