<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain;

use AIC\Workflow\Domain\Guard\Hold;
use LogicException;

/**
 * A run's state (#100), immutable: every change is a new value, and only the
 * engine saves it. A run walks plan, code, check and complete in order, one
 * phase at a time; it ends when complete passes.
 */
final readonly class RunState
{
    /**
     * @param RunStart                   $runStart     Its id, start, base commit and spec.
     * @param Ticket                     $ticket       The ticket it works.
     * @param RunPhases                  $runPhases    Each phase's status.
     * @param Phase|null                 $currentPhase The phase it is at; null once it ended.
     * @param array<string, int>         $attempts     Attempts per phase, by name.
     * @param array<string, PhaseReport> $reports      The last report per phase, by name.
     * @param list<RunNote>              $notes        What it recorded without failing on it.
     * @param Hold|null                  $awaiting     What it waits on (#102); null while the agent may work it.
     */
    public function __construct(
        public RunStart $runStart,
        public Ticket $ticket,
        public RunPhases $runPhases,
        public ?Phase $currentPhase,
        public array $attempts,
        public array $reports,
        public array $notes,
        public ?Hold $awaiting = null,
    ) {
    }

    /**
     * @param Phase $phase A phase.
     *
     * @return int How many times it was attempted.
     */
    public function attempt(Phase $phase): int
    {
        return $this->attempts[$phase->value] ?? 0;
    }

    /**
     * @param PhaseReport $phaseReport An attempt at the current phase.
     *
     * @return self The run with it counted and kept as the phase's last report.
     */
    public function withReport(PhaseReport $phaseReport): self
    {
        $attempts = [...$this->attempts, $phaseReport->phase->value => $phaseReport->attempt];
        $reports = [...$this->reports, $phaseReport->phase->value => $phaseReport];

        return new self(
            $this->runStart,
            $this->ticket,
            $this->runPhases,
            $this->currentPhase,
            $attempts,
            $reports,
            $this->notes,
            $this->awaiting,
        );
    }

    /**
     * @param Phase $phase The phase to make current.
     *
     * @throws LogicException When the run ended, or the phase is not the next one: no skipping, no going back.
     *
     * @return self The run with the current phase passed and this one active.
     */
    public function advanceTo(Phase $phase): self
    {
        if (!$this->currentPhase instanceof Phase) {
            throw new LogicException('Run ' . $this->runStart->runId . ' has ended; it cannot advance.');
        }

        if ($this->currentPhase->next() !== $phase) {
            throw new LogicException('Run ' . $this->runStart->runId . ' is at ' . $this->currentPhase->value . '; ' . $phase->value
                . ' is not the next phase.');
        }

        $runPhases = $this->passed($this->currentPhase)->with($phase, PhaseStatus::Active);

        return new self($this->runStart, $this->ticket, $runPhases, $phase, $this->attempts, $this->reports, $this->notes, $this->awaiting);
    }

    /**
     * @throws LogicException When the run is not at complete.
     *
     * @return self The run ended: complete passed, no current phase.
     */
    public function completed(): self
    {
        if ($this->currentPhase !== Phase::Complete) {
            throw new LogicException('Run ' . $this->runStart->runId . ' is not at complete; it cannot end.');
        }

        $runPhases = $this->passed(Phase::Complete);

        return new self($this->runStart, $this->ticket, $runPhases, null, $this->attempts, $this->reports, $this->notes, $this->awaiting);
    }

    /**
     * @param Ticket $ticket The ticket as the engine last wrote it.
     *
     * @return self The run with it.
     */
    public function withTicket(Ticket $ticket): self
    {
        return new self(
            $this->runStart,
            $ticket,
            $this->runPhases,
            $this->currentPhase,
            $this->attempts,
            $this->reports,
            $this->notes,
            $this->awaiting,
        );
    }

    /**
     * @param Hold $hold What it now waits on.
     *
     * @return self The run, held.
     */
    public function held(Hold $hold): self
    {
        return new self(
            $this->runStart,
            $this->ticket,
            $this->runPhases,
            $this->currentPhase,
            $this->attempts,
            $this->reports,
            $this->notes,
            $hold,
        );
    }

    /**
     * @return self The run, its hold cleared.
     */
    public function resumed(): self
    {
        return new self(
            $this->runStart,
            $this->ticket,
            $this->runPhases,
            $this->currentPhase,
            $this->attempts,
            $this->reports,
            $this->notes,
        );
    }

    /**
     * @param RunNote $runNote A note.
     *
     * @return self The run with it kept.
     */
    public function withNote(RunNote $runNote): self
    {
        $notes = [...$this->notes, $runNote];

        return new self(
            $this->runStart,
            $this->ticket,
            $this->runPhases,
            $this->currentPhase,
            $this->attempts,
            $this->reports,
            $notes,
            $this->awaiting,
        );
    }

    /**
     * @param Phase $phase A phase.
     *
     * @return RunPhases The phases with it passed, when it is active; as they are otherwise.
     */
    private function passed(Phase $phase): RunPhases
    {
        return $this->runPhases->status($phase) === PhaseStatus::Active
            ? $this->runPhases->with($phase, PhaseStatus::Passed)
            : $this->runPhases;
    }
}
