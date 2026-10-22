<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Workflow\Domain\CheckRow;
use AIC\Workflow\Domain\Outcome;
use AIC\Workflow\Domain\Phase;
use AIC\Workflow\Domain\PhaseReport;
use AIC\Workflow\Domain\PhaseStatus;
use AIC\Workflow\Domain\RunOutcome;
use AIC\Workflow\Domain\RunPhases;
use AIC\Workflow\Domain\RunStart;
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
     * @param RunStore         $runStore     The run's store.
     * @param TicketSource     $ticketSource The tickets.
     * @param RunJournal       $runJournal   The events and the ticket's moves.
     * @param RunHost          $runHost      The commit and the ids.
     * @param UtcTime          $utcTime      The clock.
     * @param list<PhaseCheck> $phaseChecks  The checks, in the order they run.
     */
    public function __construct(
        private RunStore $runStore,
        private TicketSource $ticketSource,
        private RunJournal $runJournal,
        private RunHost $runHost,
        private UtcTime $utcTime,
        private array $phaseChecks,
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
            [$runState, $notes] = $this->begin($ticketId);

            return $this->work($runState, $notes);
        }

        if ($ticketId !== '' && $ticketId !== $runState->ticket->id) {
            throw new WorkflowRefusal('run ' . $runState->runStart->runId . ' is open on ticket ' . $runState->ticket->id
                . '; finish it, or have the operator run bin/aic workflow:reset.');
        }

        return $this->work($runState, []);
    }

    /**
     * @param string $ticketId The ticket.
     *
     * @throws WorkflowRefusal When there is no ticket, or it is finished.
     *
     * @return array{RunState, list<string>} The run, begun and saved, and its notes.
     */
    private function begin(string $ticketId): array
    {
        if ($ticketId === '') {
            throw new WorkflowRefusal('no run is open; begin one on a ticket: bin/aic workflow:run <ticket>.');
        }

        $ticket = $this->ticketSource->get($ticketId);

        if ($ticket->status->isTerminal()) {
            throw new WorkflowRefusal('ticket ' . $ticket->id . ' is ' . $ticket->status->value . '; a run works an open ticket.');
        }

        $runStart = new RunStart(
            $this->runHost->newId('run', 6),
            $this->utcTime->now(),
            $this->runHost->baseCommit(),
            'var/workflow/spec-' . $ticket->number . '.md',
        );
        $runState = new RunState($runStart, $ticket, new RunPhases([Phase::Plan->value => PhaseStatus::Active]), Phase::Plan, [], [], []);
        $this->runStore->save($runState);
        $notes = $this->runJournal->started($runState);
        [$runState, $moved] = $this->runJournal->ticket($runState, TicketStatus::InProgress, Phase::Plan->ticketPhase());
        $this->runStore->save($runState);

        return [$runState, [...$notes, ...$moved]];
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
            $this->runStore->save($runState);

            $attempted = $this->runJournal->attempted($runState, $phaseReport, Outcome::Blocked);

            return new RunOutcome(Outcome::Blocked, $runState, $phaseReport, [...$notes, ...$attempted]);
        }

        return $next instanceof Phase
            ? $this->advance($runState, $phaseReport, $next, $notes)
            : $this->complete($runState, $phaseReport, $notes);
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
