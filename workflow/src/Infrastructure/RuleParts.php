<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use AIC\Workflow\Application\HouseRuleCheck;
use AIC\Workflow\Application\PhaseCheck;
use AIC\Workflow\Application\RuleFreezeCheck;
use AIC\Workflow\Application\RuleInput;
use AIC\Workflow\Application\RuleProbeCheck;
use AIC\Workflow\Application\RuleProbes;
use AIC\Workflow\Application\RuleRunner;
use AIC\Workflow\Domain\Phase;
use AIC\Workflow\Domain\WorkflowSettings;

/**
 * The house rules' checks for one project (#104): at plan the freeze and
 * the probes, at every later exit the freeze and one `rule:<id>` per rule.
 */
final readonly class RuleParts
{
    /**
     * @param string           $root             The project's root.
     * @param WorkflowSettings $workflowSettings Its settings, naming its rules.
     * @param EvidenceParts    $evidenceParts    The store, the subject and the host.
     */
    public function __construct(
        private string $root,
        private WorkflowSettings $workflowSettings,
        private EvidenceParts $evidenceParts,
    ) {
    }

    /**
     * @return list<PhaseCheck> Plan's: `rules`, then `probes`.
     */
    public function plan(): array
    {
        $parts = $this->evidenceParts;
        $ruleProbes = new RuleProbes(
            new ProbeFixtures($this->root, $this->root . '/' . RunStateFile::DIRECTORY . '/rule-probe'),
            $this->executor(),
            $this->input(),
        );

        return [
            new RuleFreezeCheck(Phase::Plan, $this->ruleFiles(), $parts->evidenceRecorder(), $parts->runSubject()),
            new RuleProbeCheck($this->workflowSettings->houseRules, $ruleProbes, $parts->evidenceLog(), $parts->runSubject()),
        ];
    }

    /**
     * @param Phase $phase Code, check or complete.
     *
     * @return list<PhaseCheck> Its `rules`, then each `rule:<id>`.
     */
    public function exit(Phase $phase): array
    {
        $parts = $this->evidenceParts;
        $checks = [new RuleFreezeCheck($phase, $this->ruleFiles(), $parts->evidenceRecorder(), $parts->runSubject())];
        $ruleInputs = new RuleInputs($this->root, $parts->runHost(), $parts->runSubject());
        $ruleRunner = new RuleRunner($this->executor(), $this->input());

        foreach ($this->workflowSettings->houseRules as $houseRule) {
            $checks[] = new HouseRuleCheck(
                $phase,
                $houseRule,
                $this->ruleFiles(),
                $ruleInputs,
                $ruleRunner,
                $parts->evidenceRecorder(),
                $parts->runSubject(),
            );
        }

        return $checks;
    }

    /**
     * @return RuleFileHashes The rule files, hashed.
     */
    public function ruleFiles(): RuleFileHashes
    {
        return new RuleFileHashes($this->root, $this->workflowSettings->houseRules);
    }

    /**
     * @return PhpRuleExecutor Runs a rule.
     */
    private function executor(): PhpRuleExecutor
    {
        return new PhpRuleExecutor($this->root, new LocalProcess($this->root));
    }

    /**
     * @return RuleInput A rule's input for this project.
     */
    private function input(): RuleInput
    {
        return new RuleInput($this->root, $this->root . '/vendor/autoload.php');
    }
}
