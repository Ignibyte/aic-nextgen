<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Workflow\Domain\HouseRule;
use AIC\Workflow\Domain\RuleCase;
use AIC\Workflow\Exception\WorkflowRefusal;

use function str_starts_with;

/**
 * A house rule's probe, run (#104): the rule on each of its cases, every
 * `held-*` exiting 0 and every `broken-*` exiting 1. A rule with no probe,
 * or with no held or no broken case, fails: a house rule ships its probe.
 */
final readonly class RuleProbes
{
    /**
     * @param RuleFixtures $ruleFixtures Lays out each case.
     * @param RuleExecutor $ruleExecutor Runs the rule.
     * @param RuleInput    $ruleInput    Writes its input.
     */
    public function __construct(
        private RuleFixtures $ruleFixtures,
        private RuleExecutor $ruleExecutor,
        private RuleInput $ruleInput,
    ) {
    }

    /**
     * @param HouseRule $houseRule A rule.
     *
     * @throws WorkflowRefusal When the fixture directory cannot be prepared.
     *
     * @return list<string> What keeps its probe from passing, one line each; empty when it bites as it should.
     */
    public function failures(HouseRule $houseRule): array
    {
        $cases = $this->ruleFixtures->cases($houseRule);

        if ($cases === null || !$this->both($cases)) {
            return [$houseRule->id . ' ships no probe with a held and a broken case in ' . $houseRule->probe() . '/.'];
        }

        $failures = [];

        foreach ($cases as $case) {
            $exitCode = $this->ruleExecutor->run($houseRule, $this->ruleInput->json($houseRule, 'probe', $case->changes))->exitCode;
            $failures = $exitCode === $case->expected()
                ? $failures
                : [...$failures, $houseRule->id . ' ' . $case->name . ' exited ' . $exitCode . ', not ' . $case->expected() . '.'];
        }

        return $failures;
    }

    /**
     * @param list<RuleCase> $cases A probe's cases.
     *
     * @return bool Whether it has a held and a broken case.
     */
    private function both(array $cases): bool
    {
        $held = false;
        $broken = false;

        foreach ($cases as $case) {
            $held = $held || str_starts_with($case->name, 'held-');
            $broken = $broken || str_starts_with($case->name, 'broken-');
        }

        return $held && $broken;
    }
}
