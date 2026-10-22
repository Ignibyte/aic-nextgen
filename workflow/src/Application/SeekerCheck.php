<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Workflow\Domain\CheckRow;
use AIC\Workflow\Domain\CheckStatus;
use AIC\Workflow\Domain\Phase;
use AIC\Workflow\Domain\RunState;
use AIC\Workflow\Domain\SeekerFinding;
use AIC\Workflow\Domain\SeekerInspection;
use AIC\Workflow\Domain\SeekerStatus;
use AIC\Workflow\Exception\WorkflowRefusal;
use Override;

use function array_filter;
use function array_map;
use function implode;

/**
 * The seeker's inspection (#103, Droost's checkpoint after the gates), the
 * last check at code and at complete. Code is due until a counted inspection
 * holds no open CRITICAL or HIGH finding; complete also needs it to have read
 * the tree as it is. A due row holds the phase and spends no retry budget:
 * it writes no verdict to `check_result`.
 */
final readonly class SeekerCheck implements PhaseCheck
{
    /**
     * What the coding agent does about a due row.
     */
    public const string REMEDY = 'dispatch the aic-seeker agent with the Agent tool, in the foreground '
        . '(not a fork, not in the background), with the prompt "Inspect the open run."; '
        . 'pipe the "## Seeker Inspection" section it returns, unchanged, to '
        . 'bin/aic workflow:seeker-report; fix what it holds and dispatch it again; then run bin/aic workflow:run.';

    /**
     * @param Phase      $phase      Code or complete.
     * @param SeekerLog  $seekerLog  The filed ledgers.
     * @param RunSubject $runSubject The tree's subject hash.
     */
    public function __construct(
        private Phase $phase,
        private SeekerLog $seekerLog,
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
     * @return CheckRow Passed with the advisory findings named; due, naming what the seeker owes or what holds.
     */
    #[Override]
    public function check(RunState $runState): CheckRow
    {
        try {
            $seekerInspection = $this->seekerLog->latestCounted($runState->runStart->runId);
        } catch (WorkflowRefusal $workflowRefusal) {
            $why = 'the seeker inspections could not be read: ' . $workflowRefusal->getMessage();

            return new CheckRow('seeker', CheckStatus::Due, $why, self::REMEDY);
        }

        $owed = $this->owed($seekerInspection, $runState);

        return $owed === '' && $seekerInspection instanceof SeekerInspection
            ? new CheckRow('seeker', CheckStatus::Passed, $this->passed($seekerInspection), '')
            : new CheckRow('seeker', CheckStatus::Due, $owed, self::REMEDY);
    }

    /**
     * @param SeekerInspection|null $seekerInspection The newest counted inspection.
     * @param RunState              $runState         The run.
     *
     * @return string What the seeker still owes the phase; empty when nothing.
     */
    private function owed(?SeekerInspection $seekerInspection, RunState $runState): string
    {
        if (!$seekerInspection instanceof SeekerInspection) {
            return 'no independent inspection that an aic-seeker returned is filed for this run yet.';
        }

        $round = 'round ' . $seekerInspection->round;
        $holding = $seekerInspection->seekerLedger->holding();
        $stale = $this->phase === Phase::Complete && $seekerInspection->seekerLedger->subject !== $this->runSubject->hash($runState);

        return match (true) {
            $holding !== [] => $round . ' holds ' . $this->lines($holding) . '.',
            $stale => 'the tree changed since ' . $round . ', so the seeker reads the delivered tree.',
            default => '',
        };
    }

    /**
     * @param SeekerInspection $seekerInspection The inspection that clears the phase.
     *
     * @return string Its row's words, the advisory findings named.
     */
    private function passed(SeekerInspection $seekerInspection): string
    {
        $advisory = array_filter(
            $seekerInspection->seekerLedger->findings,
            static fn (SeekerFinding $seekerFinding): bool => $seekerFinding->seekerStatus === SeekerStatus::Open,
        );

        return 'round ' . $seekerInspection->round . ' (independent, verified) holds no open CRITICAL or HIGH finding'
            . ($advisory === [] ? '.' : '; advisory: ' . $this->lines($advisory) . '.');
    }

    /**
     * @param array<int, SeekerFinding> $findings Findings.
     *
     * @return string Each in one line, joined.
     */
    private function lines(array $findings): string
    {
        return implode('; ', array_map(static fn (SeekerFinding $seekerFinding): string => $seekerFinding->line(), $findings));
    }
}
