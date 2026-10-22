<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Workflow\Domain\CheckRecord;
use AIC\Workflow\Domain\HouseRule;
use AIC\Workflow\Domain\RuleChange;

/**
 * Runs a house rule on a change and reads its verdict (#104).
 */
final readonly class RuleRunner
{
    /**
     * @param RuleExecutor $ruleExecutor Runs the rule.
     * @param RuleInput    $ruleInput    Writes its input.
     * @param RuleVerdict  $ruleVerdict  Reads its exit.
     */
    public function __construct(
        private RuleExecutor $ruleExecutor,
        private RuleInput $ruleInput,
        private RuleVerdict $ruleVerdict = new RuleVerdict(),
    ) {
    }

    /**
     * @param string           $name        The check, `rule:<id>`.
     * @param HouseRule        $houseRule   The rule.
     * @param string           $phase       The phase.
     * @param list<RuleChange> $changes     The run's changes.
     * @param string           $subjectHash The tree.
     *
     * @return CheckRecord Its verdict.
     */
    public function verdict(
        string $name,
        HouseRule $houseRule,
        string $phase,
        array $changes,
        string $subjectHash,
    ): CheckRecord {
        $processResult = $this->ruleExecutor->run($houseRule, $this->ruleInput->json($houseRule, $phase, $changes));

        return $this->ruleVerdict->record($name, $processResult, $subjectHash);
    }
}
