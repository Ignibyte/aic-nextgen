<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Workflow\Domain\CheckRecord;
use AIC\Workflow\Domain\CheckState;
use AIC\Workflow\Domain\RunState;
use AIC\Workflow\Domain\SeekerLedger;
use AIC\Workflow\Exception\WorkflowRefusal;

/**
 * The tree a ledger is filed against (#103): it must be the one the seeker
 * read (its `Subject:`), and the gates must have passed on it, since the
 * seeker reads code that passes the gates (Droost's checkpoint after them).
 */
final readonly class SeekerTree
{
    /**
     * Why a ledger on a tree with no passing gate verdict is refused.
     */
    private const string NO_GATE = 'the seeker reads code that passes the gates, and no passing gate verdict is on this tree: '
        . 'run bin/aic workflow:run first.';

    /**
     * @param RunSubject  $runSubject  The tree's subject hash.
     * @param EvidenceLog $evidenceLog The gate's newest verdict.
     */
    public function __construct(
        private RunSubject $runSubject,
        private EvidenceLog $evidenceLog,
    ) {
    }

    /**
     * @param RunState     $runState     The run.
     * @param SeekerLedger $seekerLedger The ledger.
     *
     * @throws WorkflowRefusal When it read another tree, or the gates have not passed on this one.
     */
    public function check(RunState $runState, SeekerLedger $seekerLedger): void
    {
        $subject = $this->runSubject->hash($runState);

        if ($seekerLedger->subject !== $subject) {
            throw new WorkflowRefusal('the ledger read subject ' . $seekerLedger->subject . ', and the tree is ' . $subject
                . ' now: dispatch the seeker again on the tree as it is.');
        }

        $gate = $this->evidenceLog->latest($runState->runStart->runId, 'gate');
        $passing = $gate instanceof CheckRecord && $gate->subjectHash === $subject
            && $gate->checkState !== CheckState::Blocked && $gate->checkState !== CheckState::Errored;

        if (!$passing) {
            throw new WorkflowRefusal(self::NO_GATE);
        }
    }
}
