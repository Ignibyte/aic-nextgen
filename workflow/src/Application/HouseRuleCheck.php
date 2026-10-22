<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Workflow\Domain\CheckRecord;
use AIC\Workflow\Domain\CheckRow;
use AIC\Workflow\Domain\CheckState;
use AIC\Workflow\Domain\Fault;
use AIC\Workflow\Domain\Findings;
use AIC\Workflow\Domain\HouseRule;
use AIC\Workflow\Domain\Phase;
use AIC\Workflow\Domain\RunState;
use AIC\Workflow\Exception\WorkflowRefusal;
use Override;

use function array_key_exists;

/**
 * One house rule at an exit after plan (#104): `rule:<id>`, the rule run on
 * the run's change, base against head. A rule whose file changed since the
 * run began does not run (the `rules` row names it); a run that changed
 * nothing is not judged, and that is a deferral, not a pass.
 */
final readonly class HouseRuleCheck implements PhaseCheck
{
    /**
     * @param Phase            $phase            The phase it runs at.
     * @param HouseRule        $houseRule        The rule.
     * @param RuleFiles        $ruleFiles        The rule files as they are now.
     * @param RuleChanges      $ruleChanges      The run's changes, as files.
     * @param RuleRunner       $ruleRunner       Runs the rule and reads its verdict.
     * @param EvidenceRecorder $evidenceRecorder Records it.
     * @param RunSubject       $runSubject       The tree's subject hash.
     */
    public function __construct(
        private Phase $phase,
        private HouseRule $houseRule,
        private RuleFiles $ruleFiles,
        private RuleChanges $ruleChanges,
        private RuleRunner $ruleRunner,
        private EvidenceRecorder $evidenceRecorder,
        private RunSubject $runSubject,
    ) {
    }

    /**
     * @return Phase The phase it runs at.
     */
    #[Override]
    public function phase(): Phase
    {
        return $this->phase;
    }

    /**
     * @param RunState $runState The run.
     *
     * @return CheckRow The rule's row.
     */
    #[Override]
    public function check(RunState $runState): CheckRow
    {
        $name = 'rule:' . $this->houseRule->id;
        $subjectHash = $this->runSubject->hash($runState);

        try {
            $checkRecord = $this->changed($runState)
                ? (new Findings($name, ['its file changed since the run began; the rules row names it.'], '', $subjectHash))->record()
                : $this->judged($runState, $name, $subjectHash);
        } catch (WorkflowRefusal $workflowRefusal) {
            return $this->evidenceRecorder->unrecorded($name, $workflowRefusal);
        }

        return $this->evidenceRecorder->row($runState, $this->phase, $checkRecord, $this->houseRule->rule);
    }

    /**
     * @param RunState $runState    The run.
     * @param string   $name        The check's name.
     * @param string   $subjectHash The tree.
     *
     * @throws WorkflowRefusal When the run's changes cannot be read.
     *
     * @return CheckRecord The rule's verdict; deferred when the run changed nothing.
     */
    private function judged(RunState $runState, string $name, string $subjectHash): CheckRecord
    {
        $changes = $this->ruleChanges->changes($runState);

        return $changes === []
            ? new CheckRecord($name, CheckState::Skipped, Fault::None, 'the run changed nothing; a deferral is not a pass.', $subjectHash)
            : $this->ruleRunner->verdict($name, $this->houseRule, $this->phase->value, $changes, $subjectHash);
    }

    /**
     * @param RunState $runState The run.
     *
     * @return bool Whether the rule's file hashes otherwise than when the run began.
     */
    private function changed(RunState $runState): bool
    {
        $frozen = $runState->runStart->ruleFiles;
        $now = $this->ruleFiles->hashes();
        $path = $this->houseRule->run;

        return !array_key_exists($path, $frozen) || !array_key_exists($path, $now) || $frozen[$path] !== $now[$path];
    }
}
