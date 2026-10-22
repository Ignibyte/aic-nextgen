<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Workflow\Application\Guard\OperatorHold;
use AIC\Workflow\Application\Guard\ToolCallIngest;
use AIC\Workflow\Domain\CheckRow;
use AIC\Workflow\Domain\Outcome;
use AIC\Workflow\Domain\Phase;
use AIC\Workflow\Domain\PhaseReport;
use AIC\Workflow\Domain\RunOutcome;
use AIC\Workflow\Domain\RunState;
use AIC\Workflow\Domain\TicketStatus;
use AIC\Workflow\Exception\WorkflowRefusal;

/**
 * The run engine (#100): begins a run on a ticket, then works its current
 * phase once per call. A phase whose checks all pass advances to the next;
 * complete passing ends the run and archives it. The engine alone saves the
 * run, and records each change after its save (RunJournal).
 */
final readonly class RunEngine
{
    /**
     * @param RunStore         $runStore       The run's store.
     * @param RunBegin         $runBegin       Begins a run on a ticket.
     * @param RunJournal       $runJournal     The events and the ticket's moves.
     * @param list<PhaseCheck> $phaseChecks    The checks, in the order they run.
     * @param OperatorHold     $operatorHold   Holds a phase for the operator, and resumes it from a terminal (#102).
     * @param ToolCallIngest   $toolCallIngest Moves the guard's ledger into the evidence store (#102).
     */
    public function __construct(
        private RunStore $runStore,
        private RunBegin $runBegin,
        private RunJournal $runJournal,
        private array $phaseChecks,
        private OperatorHold $operatorHold,
        private ToolCallIngest $toolCallIngest,
    ) {
    }

    /**
     * @param string $ticketId The ticket to begin a run on, when none is open; empty to work the open run.
     *
     * @throws WorkflowRefusal When there is no run and no ticket, the ticket cannot run, or a run is open on another.
     *
     * @return RunOutcome What this call did.
     */
    public function run(string $ticketId): RunOutcome
    {
        $runState = $this->runStore->load();

        if (!$runState instanceof RunState) {
            [$runState, $notes] = $this->runBegin->begin($ticketId);

            return $this->work($runState, $notes);
        }

        if ($ticketId !== '' && $ticketId !== $runState->ticket->id) {
            throw new WorkflowRefusal('run ' . $runState->runStart->runId . ' is open on ticket ' . $runState->ticket->id
                . '; finish it, or have the operator run bin/aic workflow:reset.');
        }

        $ingested = $this->toolCallIngest->ingest($runState);
        [$runState, $resumed] = $this->operatorHold->resume($runState);

        return $this->work($runState, [...$ingested, ...$resumed]);
    }

    /**
     * @param RunState     $runState The open run.
     * @param list<string> $notes    Notes so far.
     *
     * @throws WorkflowRefusal When the run cannot be saved or archived.
     *
     * @return RunOutcome What working its phase did.
     */
    private function work(RunState $runState, array $notes): RunOutcome
    {
        $phase = $runState->currentPhase ?? Phase::Complete;
        $phaseReport = new PhaseReport($phase, $runState->attempt($phase) + 1, $this->rows($runState, $phase));
        $runState = $runState->withReport($phaseReport);
        $next = $phase->next();

        if (!$phaseReport->advance()) {
            return $this->held($runState, $phaseReport, $notes);
        }

        return $next instanceof Phase
            ? $this->advance($runState, $phaseReport, $next, $notes)
            : $this->complete($runState, $phaseReport, $notes);
    }

    /**
     * @param RunState     $runState    The run, its attempt recorded and held by at least one row.
     * @param PhaseReport  $phaseReport The attempt.
     * @param list<string> $notes       Notes so far.
     *
     * @throws WorkflowRefusal When the run cannot be saved.
     *
     * @return RunOutcome Blocked; held for the operator when a row only the operator can clear holds it (#102).
     */
    private function held(RunState $runState, PhaseReport $phaseReport, array $notes): RunOutcome
    {
        $outcome = $phaseReport->held() === []
            ? Outcome::Blocked
            : Outcome::Held;
        $this->runStore->save($runState);
        $attempted = $this->runJournal->attempted($runState, $phaseReport, $outcome);
        [$runState, $held] = $outcome === Outcome::Held
            ? $this->operatorHold->hold($runState, $phaseReport)
            : [$runState, []];

        return new RunOutcome($outcome, $runState, $phaseReport, [...$notes, ...$attempted, ...$held]);
    }

    /**
     * @param RunState     $runState    The run, its current phase's checks passed.
     * @param PhaseReport  $phaseReport The attempt.
     * @param Phase        $phase       The phase after it.
     * @param list<string> $notes       Notes so far.
     *
     * @throws WorkflowRefusal When the run cannot be saved.
     *
     * @return RunOutcome The run, at the next phase.
     */
    private function advance(RunState $runState, PhaseReport $phaseReport, Phase $phase, array $notes): RunOutcome
    {
        $runState = $runState->advanceTo($phase);
        $this->runStore->save($runState);
        $notes = [
            ...$notes,
            ...$this->runJournal->attempted($runState, $phaseReport, Outcome::Advanced),
            ...$this->runJournal->moved($runState, $phaseReport->phase, $phase),
        ];
        [$runState, $moved] = $this->runJournal->ticket($runState, $runState->ticket->status, $phase->ticketPhase());
        $this->runStore->save($runState);

        return new RunOutcome(Outcome::Advanced, $runState, $phaseReport, [...$notes, ...$moved]);
    }

    /**
     * @param RunState     $runState    The run at complete, its checks passed.
     * @param PhaseReport  $phaseReport The attempt.
     * @param list<string> $notes       Notes so far.
     *
     * @throws WorkflowRefusal When the run cannot be saved or archived.
     *
     * @return RunOutcome The run, completed and archived.
     */
    private function complete(RunState $runState, PhaseReport $phaseReport, array $notes): RunOutcome
    {
        $runState = $runState->completed();
        $this->runStore->save($runState);
        $notes = [
            ...$notes,
            ...$this->runJournal->attempted($runState, $phaseReport, Outcome::Completed),
            ...$this->runJournal->moved($runState, Phase::Complete, null),
        ];
        [$runState, $moved] = $this->runJournal->ticket($runState, TicketStatus::InReview, Phase::Complete->ticketPhase());
        $this->runStore->save($runState);
        $runOutcome = new RunOutcome(Outcome::Completed, $runState, $phaseReport, [...$notes, ...$moved]);
        $completed = $this->runJournal->completed($runOutcome);
        $this->runStore->archive($runState);

        return new RunOutcome(Outcome::Completed, $runState, $phaseReport, [...$runOutcome->notes, ...$completed]);
    }

    /**
     * @param RunState $runState The run.
     * @param Phase    $phase    Its current phase.
     *
     * @return list<CheckRow> Each check due at the phase's row, in order.
     */
    private function rows(RunState $runState, Phase $phase): array
    {
        $rows = [];

        foreach ($this->phaseChecks as $phaseCheck) {
            if ($phaseCheck->phase() !== $phase) {
                continue;
            }

            $rows[] = $phaseCheck->check($runState);
        }

        return $rows;
    }
}
