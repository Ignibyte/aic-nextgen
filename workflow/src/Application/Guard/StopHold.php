<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Guard;

use AIC\Workflow\Domain\CheckRow;
use AIC\Workflow\Domain\Guard\Enforcement;
use AIC\Workflow\Domain\Guard\GuardScene;
use AIC\Workflow\Domain\Guard\GuardVerdict;
use AIC\Workflow\Domain\Guard\Hold;
use AIC\Workflow\Domain\Mode;
use AIC\Workflow\Domain\Phase;
use AIC\Workflow\Domain\PhaseReport;
use AIC\Workflow\Domain\RunState;

use function array_map;
use function implode;

/**
 * The Stop hold (#102, #117): with a run open at a phase and nothing awaited,
 * hard enforcement holds the turn mid-phase in either mode; soft holds an
 * agentic run, which promises no stops, and nudges an interactive one once
 * per run and phase; off lets it end. There is no ceiling on the hold (AIC's
 * rule): a hold the engine sets for the operator, or a question waiting for
 * an answer, releases it and says what it waits on.
 */
final readonly class StopHold
{
    /**
     * @param GuardLedger $guardLedger Whether the soft nudge was already given.
     */
    public function __construct(
        private GuardLedger $guardLedger,
    ) {
    }

    /**
     * @param GuardScene $guardScene The run and the levers.
     *
     * @return GuardVerdict Refused mid-phase, naming what holds it; nudged or allowed otherwise, naming what is awaited.
     */
    public function check(GuardScene $guardScene): GuardVerdict
    {
        $runState = $guardScene->runState;
        $phase = $guardScene->phase();
        $enforcement = $guardScene->enforcement();

        if (!$runState instanceof RunState || !$phase instanceof Phase || $enforcement === Enforcement::Off) {
            return new GuardVerdict();
        }

        if ($runState->awaiting instanceof Hold) {
            return self::awaited($runState->awaiting);
        }

        $held = 'run ' . $runState->runStart->runId . ' is at ' . $phase->value . ' and the turn may not end mid-phase. ';
        $holding = $held . $this->holding($runState->reports[$phase->value] ?? null);

        $agentic = 'an agentic run makes no stops. ' . $holding;

        return match (true) {
            $enforcement === Enforcement::Hard => new GuardVerdict('refuse', 'stop-hold', $holding),
            $guardScene->mode() === Mode::Agentic => new GuardVerdict('refuse', 'stop-hold:agentic', $agentic),
            default => $this->nudge($runState->runStart->runId, $phase, $holding),
        };
    }

    /**
     * @param string $run     The run.
     * @param Phase  $phase   Its phase.
     * @param string $holding What holds it.
     *
     * @return GuardVerdict The soft nudge of an interactive run: in words the first time at the phase, silent after.
     */
    private function nudge(string $run, Phase $phase, string $holding): GuardVerdict
    {
        $warned = $this->guardLedger->warned($run, $phase->value, 'stop-hold:soft');

        return new GuardVerdict('nudge', 'stop-hold:soft', $warned ? '' : $holding);
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

    /**
     * @param Hold $hold What the run waits on.
     *
     * @return GuardVerdict The stop allowed, naming the hold.
     */
    private static function awaited(Hold $hold): GuardVerdict
    {
        return match ($hold->kind) {
            'question' => new GuardVerdict('nudge', 'question-hold', 'the run waits for the operator to answer: ' . $hold->question),
            'conversation', 'complete' => new GuardVerdict('nudge', 'confirm-hold', 'the operator confirms the exit: ' . $hold->question),
            default => new GuardVerdict('nudge', 'operator-hold', 'the run waits on the operator at ' . $hold->phase . ': ' . $hold->why),
        };
    }
}
