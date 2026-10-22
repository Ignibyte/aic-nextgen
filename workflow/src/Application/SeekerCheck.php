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
 * The seeker's one pass (#103, advisory since #127), the last check at code.
 * It is due until a counted inspection is filed for the run, then passes and
 * names every open finding, at every severity: none holds the run. A due row
 * holds the phase and spends no retry budget: it writes no verdict to
 * `check_result`.
 */
final readonly class SeekerCheck implements PhaseCheck
{
    /**
     * What the coding agent does about a due row.
     */
    public const string REMEDY = 'dispatch the aic-seeker agent with the Agent tool, in the foreground '
        . '(not a fork, not in the background), with the prompt "Inspect the open run."; '
        . 'pipe the "## Seeker Inspection" section it returns, unchanged, to '
        . 'bin/aic workflow:seeker-report; then run bin/aic workflow:run. Its findings are advisory.';

    /**
     * Why the row is due before the pass.
     */
    private const string NONE = 'no independent inspection that an aic-seeker returned is filed for this run yet.';

    /**
     * @param SeekerLog $seekerLog The filed ledgers.
     */
    public function __construct(
        private SeekerLog $seekerLog,
    ) {
    }

    /**
     * @return Phase Code.
     */
    #[Override]
    public function phase(): Phase
    {
        return Phase::Code;
    }

    /**
     * @param RunState $runState The run.
     *
     * @return CheckRow Passed with every open finding named; due while no counted inspection is filed.
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

        return $seekerInspection instanceof SeekerInspection
            ? new CheckRow('seeker', CheckStatus::Passed, $this->passed($seekerInspection), '')
            : new CheckRow('seeker', CheckStatus::Due, self::NONE, self::REMEDY);
    }

    /**
     * @param SeekerInspection $seekerInspection The inspection that clears the phase.
     *
     * @return string Its row's words, every open finding named as advisory.
     */
    private function passed(SeekerInspection $seekerInspection): string
    {
        $open = array_filter(
            $seekerInspection->seekerLedger->findings,
            static fn (SeekerFinding $seekerFinding): bool => $seekerFinding->seekerStatus === SeekerStatus::Open,
        );
        $lines = implode('; ', array_map(static fn (SeekerFinding $seekerFinding): string => $seekerFinding->line(), $open));

        return 'round ' . $seekerInspection->round . ' is filed and counted'
            . ($open === [] ? '; no open findings.' : '; advisory, none holds: ' . $lines . '.');
    }
}
