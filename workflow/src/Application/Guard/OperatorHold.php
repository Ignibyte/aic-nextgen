<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Guard;

use AIC\Workflow\Application\RunHost;
use AIC\Workflow\Application\RunJournal;
use AIC\Workflow\Application\RunStore;
use AIC\Workflow\Application\UtcTime;
use AIC\Workflow\Domain\CheckRow;
use AIC\Workflow\Domain\Guard\Hold;
use AIC\Workflow\Domain\Phase;
use AIC\Workflow\Domain\PhaseReport;
use AIC\Workflow\Domain\RunState;
use AIC\Workflow\Exception\WorkflowRefusal;

use function array_map;
use function implode;

/**
 * The operator hold (#102): a phase whose report holds a row only the
 * operator can clear leaves the run waiting on the operator, with
 * `awaiting` in `run.json` and `run.held` logged. Stop then lets the agent's
 * turn end, the agent's `workflow:run` is refused, and the operator's own,
 * from a terminal, clears it (`run.resumed`) and works the phase again.
 */
final readonly class OperatorHold
{
    /**
     * @param RunStore   $runStore   Saves the run.
     * @param RunJournal $runJournal Logs the hold and the resume.
     * @param RunHost    $runHost    Whether the operator is at a terminal.
     * @param UtcTime    $utcTime    The clock.
     */
    public function __construct(
        private RunStore $runStore,
        private RunJournal $runJournal,
        private RunHost $runHost,
        private UtcTime $utcTime,
    ) {
    }

    /**
     * @param RunState $runState The open run.
     *
     * @throws WorkflowRefusal When it waits for an answer (#117); when it waits on the operator and the caller is no terminal;
     *                         or when it cannot be saved.
     *
     * @return array{RunState, list<string>} The run, free to work, and the resume's notes.
     */
    public function resume(RunState $runState): array
    {
        $hold = $runState->awaiting;

        if (!$hold instanceof Hold) {
            return [$runState, []];
        }

        if ($hold->isQuestion()) {
            throw new WorkflowRefusal('waiting for the operator to answer at ' . $hold->phase . ': ' . $hold->question
                . ' The operator answers from a terminal with bin/aic workflow:answer; workflow:run waits until then.');
        }

        if (!$this->runHost->operatorTerminal()) {
            throw new WorkflowRefusal('waiting on the operator at ' . $hold->phase . ', who resumes it from a terminal: ' . $hold->why);
        }

        $resumed = $runState->resumed();
        $this->runStore->save($resumed);

        return [$resumed, $this->runJournal->resumed($resumed, $hold)];
    }

    /**
     * @param RunState    $runState    The run, its attempt recorded.
     * @param PhaseReport $phaseReport The attempt, with at least one held row.
     *
     * @throws WorkflowRefusal When the run cannot be saved.
     *
     * @return array{RunState, list<string>} The run, held and saved, and the hold's notes.
     */
    public function hold(RunState $runState, PhaseReport $phaseReport): array
    {
        $why = implode(' ', array_map(
            static fn (CheckRow $checkRow): string => $checkRow->check . ': ' . $checkRow->why . ' ' . $checkRow->remedy,
            $phaseReport->held(),
        ));
        $phase = $runState->currentPhase ?? Phase::Complete;
        $hold = new Hold('operator', $phase->value, $why, $this->utcTime->now());
        $held = $runState->held($hold);
        $this->runStore->save($held);

        return [$held, $this->runJournal->held($held, $hold)];
    }
}
