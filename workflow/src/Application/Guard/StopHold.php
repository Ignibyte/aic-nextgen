<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Guard;

use AIC\Workflow\Domain\CheckRow;
use AIC\Workflow\Domain\Guard\Enforcement;
use AIC\Workflow\Domain\Guard\GuardScene;
use AIC\Workflow\Domain\Guard\GuardVerdict;
use AIC\Workflow\Domain\Guard\Hold;
use AIC\Workflow\Domain\Phase;
use AIC\Workflow\Domain\PhaseReport;
use AIC\Workflow\Domain\RunState;

use function array_map;
use function implode;

/**
 * The Stop hold (#102): with a run open at a phase, enforcement on and
 * nothing awaited, the agent's turn does not end. There is no ceiling on
 * the hold (AIC's rule): a phase only the operator can clear holds the run
 * for the operator instead, and that releases it.
 */
final readonly class StopHold
{
    /**
     * @param GuardScene $guardScene The run and the levers.
     *
     * @return GuardVerdict Refused mid-phase, naming what holds it; allowed otherwise, naming an operator hold.
     */
    public function check(GuardScene $guardScene): GuardVerdict
    {
        $runState = $guardScene->runState;
        $phase = $guardScene->phase();

        if (
            !$runState instanceof RunState
            || !$phase instanceof Phase
            || $guardScene->enforcement() === Enforcement::Off
        ) {
            return new GuardVerdict();
        }

        $hold = $runState->awaiting;

        if ($hold instanceof Hold) {
            return new GuardVerdict('nudge', 'operator-hold', 'the run waits on the operator at ' . $hold->phase . ': ' . $hold->why);
        }

        $held = 'run ' . $runState->runStart->runId . ' is at ' . $phase->value . ' and the turn may not end mid-phase. ';

        return new GuardVerdict('refuse', 'stop-hold', $held . $this->holding($runState->reports[$phase->value] ?? null));
    }

    /**
     * @param PhaseReport|null $phaseReport The phase's last report; null when it was not worked yet.
     *
     * @return string What holds it, and what to do.
     */
    private function holding(?PhaseReport $phaseReport): string
    {
        if (!$phaseReport instanceof PhaseReport) {
            return 'It is not worked yet: run bin/aic workflow:run.';
        }

        $rows = array_map(
            static fn (CheckRow $checkRow): string => $checkRow->check . ' (' . $checkRow->checkStatus->value . '): '
                . $checkRow->why . ' ' . $checkRow->remedy,
            $phaseReport->blocked(),
        );

        return 'Attempt ' . $phaseReport->attempt . ' holds on: ' . implode(' ', $rows) . ' Then run bin/aic workflow:run.';
    }
}
