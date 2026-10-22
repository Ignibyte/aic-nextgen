<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Workflow\Domain\Guard\Enforcement;
use AIC\Workflow\Domain\Phase;
use AIC\Workflow\Domain\PhaseStatus;
use AIC\Workflow\Domain\RunPhases;
use AIC\Workflow\Domain\RunStart;
use AIC\Workflow\Domain\RunState;
use AIC\Workflow\Domain\TicketStatus;
use AIC\Workflow\Exception\WorkflowRefusal;

/**
 * Begins a run on a ticket (#100; out of RunEngine in #102): a fresh id, the
 * base commit, the spec's path, the guard's enforcement and the house rules'
 * files frozen, plan
 * active, the run saved and logged, the ticket moved to in-progress.
 */
final readonly class RunBegin
{
    /**
     * @param RunStore     $runStore     The run's store.
     * @param TicketSource $ticketSource The tickets.
     * @param RunJournal   $runJournal   The events and the ticket's moves.
     * @param RunHost      $runHost      The commit and the ids.
     * @param UtcTime      $utcTime      The clock.
     * @param Enforcement  $enforcement  The guard's enforcement, frozen into the run (#102).
     * @param RuleFiles    $ruleFiles    The house rules' files, hashed into the run (#104).
     */
    public function __construct(
        private RunStore $runStore,
        private TicketSource $ticketSource,
        private RunJournal $runJournal,
        private RunHost $runHost,
        private UtcTime $utcTime,
        private Enforcement $enforcement,
        private RuleFiles $ruleFiles,
    ) {
    }

    /**
     * @param string $ticketId The ticket.
     *
     * @throws WorkflowRefusal When there is no ticket, or it is finished.
     *
     * @return array{RunState, list<string>} The run, begun and saved, and its notes.
     */
    public function begin(string $ticketId): array
    {
        if ($ticketId === '') {
            throw new WorkflowRefusal('no run is open; begin one on a ticket: bin/aic workflow:run <ticket>.');
        }

        $ticket = $this->ticketSource->get($ticketId);

        if ($ticket->status->isTerminal()) {
            throw new WorkflowRefusal('ticket ' . $ticket->id . ' is ' . $ticket->status->value . '; a run works an open ticket.');
        }

        $runPhases = new RunPhases([Phase::Plan->value => PhaseStatus::Active]);
        $runState = new RunState($this->start($ticket->number), $ticket, $runPhases, Phase::Plan, [], [], []);
        $this->runStore->save($runState);
        $notes = $this->runJournal->started($runState);
        [$runState, $moved] = $this->runJournal->ticket($runState, TicketStatus::InProgress, Phase::Plan->ticketPhase());
        $this->runStore->save($runState);

        return [$runState, [...$notes, ...$moved]];
    }

    /**
     * @param int $number The ticket's number.
     *
     * @return RunStart The run's start: a fresh id, now, the base commit, the spec's path and what it freezes.
     */
    private function start(int $number): RunStart
    {
        return new RunStart(
            $this->runHost->newId('run', 6),
            $this->utcTime->now(),
            $this->runHost->baseCommit(),
            'var/workflow/spec-' . $number . '.md',
            $this->enforcement,
            $this->ruleFiles->hashes(),
        );
    }
}
