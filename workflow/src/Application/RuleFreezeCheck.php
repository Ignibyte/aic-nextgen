<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Workflow\Domain\CheckRow;
use AIC\Workflow\Domain\Findings;
use AIC\Workflow\Domain\Phase;
use AIC\Workflow\Domain\RunState;
use AIC\Workflow\Exception\WorkflowRefusal;
use Override;

use function array_key_exists;
use function array_keys;
use function array_unique;
use function implode;

/**
 * The house rules' freeze (#104): every file in the run's `rule_files`
 * hashes as it did when the run began, and the settings name no rule the run
 * did not freeze. A change holds the run, where Droost only names it: the
 * agent restores the bytes, or the operator resets the run and begins it
 * again on the new rules.
 */
final readonly class RuleFreezeCheck implements PhaseCheck
{
    /**
     * What to do about a changed rule file.
     */
    private const string REMEDY = 'restore each file as it was, or have the operator reset the run and begin it again on the new rules.';

    /**
     * @param Phase            $phase            The phase it runs at.
     * @param RuleFiles        $ruleFiles        The rule files as they are now.
     * @param EvidenceRecorder $evidenceRecorder Records the verdict.
     * @param RunSubject       $runSubject       The tree's subject hash.
     */
    public function __construct(
        private Phase $phase,
        private RuleFiles $ruleFiles,
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
     * @throws WorkflowRefusal When a called workflow operation refuses its input or environment.
     *
     * @return CheckRow Passed when the rule files are as they were frozen; failed, naming each that is not.
     */
    #[Override]
    public function check(RunState $runState): CheckRow
    {
        $changed = $this->changed($runState->runStart->ruleFiles, $this->ruleFiles->hashes());
        $failures = $changed === []
            ? []
            : ['changed since the run began: ' . implode(', ', $changed) . '.'];
        $findings = new Findings('rules', $failures, 'the house rules are as the run froze them.', $this->runSubject->hash($runState));

        return $this->evidenceRecorder->row($runState, $this->phase, $findings->record(), self::REMEDY);
    }

    /**
     * @param array<string, string|null> $frozen The hashes at begin.
     * @param array<string, string|null> $now    The hashes now.
     *
     * @return list<string> Each path whose hash differs, or that only one of them names.
     */
    private function changed(array $frozen, array $now): array
    {
        $changed = [];

        foreach (array_unique([...array_keys($frozen), ...array_keys($now)]) as $path) {
            if (array_key_exists($path, $frozen) && array_key_exists($path, $now) && $frozen[$path] === $now[$path]) {
                continue;
            }

            $changed[] = $path;
        }

        return $changed;
    }
}
