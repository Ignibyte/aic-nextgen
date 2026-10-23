<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Workflow\Domain\CheckRecord;
use AIC\Workflow\Domain\CheckRow;
use AIC\Workflow\Domain\CheckState;
use AIC\Workflow\Domain\CheckStatus;
use AIC\Workflow\Domain\Fault;
use AIC\Workflow\Domain\HouseRule;
use AIC\Workflow\Domain\Phase;
use AIC\Workflow\Domain\RunState;
use AIC\Workflow\Exception\WorkflowRefusal;
use Override;

use function implode;

/**
 * Plan's probe check (#104): every house rule bites on its own planted
 * cases before the run relies on it. A rule that does not is the
 * environment's, never the agent's: the row holds plan for the operator, who
 * fixes the rule. Each rule's result is recorded as `probe:<id>`.
 */
final readonly class RuleProbeCheck implements PhaseCheck
{
    /**
     * What to do about a rule that does not bite.
     */
    private const string REMEDY = 'the operator fixes each rule or its probe, then runs bin/aic workflow:run.';

    /**
     * @param list<HouseRule> $houseRules  The project's rules.
     * @param RuleProbes      $ruleProbes  Runs each rule's probe.
     * @param EvidenceLog     $evidenceLog Keeps each rule's result.
     * @param RunSubject      $runSubject  The tree's subject hash.
     */
    public function __construct(
        private array $houseRules,
        private RuleProbes $ruleProbes,
        private EvidenceLog $evidenceLog,
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
     * @return CheckRow Passed when every rule's probe passes; held for the operator, naming each that does not.
     */
    #[Override]
    public function check(RunState $runState): CheckRow
    {
        $failures = [];

        try {
            foreach ($this->houseRules as $houseRule) {
                $failures = [...$failures, ...$this->probe($runState, $houseRule)];
            }
        } catch (WorkflowRefusal $workflowRefusal) {
            return new CheckRow('probes', CheckStatus::Held, "a probe's result was not recorded: " . $workflowRefusal->getMessage(), '');
        }

        $passed = $this->houseRules === []
            ? 'the project has no house rules.'
            : 'every house rule bites on its probe.';

        return $failures === []
            ? new CheckRow('probes', CheckStatus::Passed, $passed, '')
            : new CheckRow('probes', CheckStatus::Held, implode(' ', $failures), self::REMEDY);
    }

    /**
     * @param RunState  $runState  The run.
     * @param HouseRule $houseRule A rule.
     *
     * @throws WorkflowRefusal When probe setup, subject hashing or result recording refuses.
     *
     * @return list<string> What keeps its probe from passing.
     */
    private function probe(RunState $runState, HouseRule $houseRule): array
    {
        $failures = $this->ruleProbes->failures($houseRule);
        $checkRecord = new CheckRecord(
            'probe:' . $houseRule->id,
            $failures === [] ? CheckState::Satisfied : CheckState::Blocked,
            $failures === [] ? Fault::None : Fault::Environment,
            $failures === [] ? 'its probe passed.' : implode(' ', $failures),
            $this->runSubject->hash($runState),
        );
        $this->evidenceLog->record($runState->runStart->runId, Phase::Plan, $runState->attempt(Phase::Plan) + 1, $checkRecord);

        return $failures;
    }
}
