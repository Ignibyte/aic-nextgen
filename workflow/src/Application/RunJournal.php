<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Workflow\Domain\EventType;
use AIC\Workflow\Domain\Outcome;
use AIC\Workflow\Domain\Phase;
use AIC\Workflow\Domain\PhaseReport;
use AIC\Workflow\Domain\Phases;
use AIC\Workflow\Domain\RunNote;
use AIC\Workflow\Domain\RunOutcome;
use AIC\Workflow\Domain\RunState;
use AIC\Workflow\Domain\TicketStatus;
use AIC\Workflow\Exception\WorkflowRefusal;

use function array_filter;
use function array_map;
use function array_values;
use function json_encode;
use function substr;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * What the engine records after each save (#100): the run's events, in
 * Droost's order, and the ticket's moves. Neither fails the run: an event
 * the log cannot take, or a move the ticket source refuses, becomes a note.
 */
final readonly class RunJournal
{
    /**
     * @param EventLog     $eventLog     The event log.
     * @param TicketSource $ticketSource The tickets.
     * @param UtcTime      $utcTime      The clock.
     */
    public function __construct(
        private EventLog $eventLog,
        private TicketSource $ticketSource,
        private UtcTime $utcTime,
    ) {
    }

    /**
     * @param RunState $runState The run, just begun.
     *
     * @return list<string> Notes: events the log could not take.
     */
    public function started(RunState $runState): array
    {
        return self::kept([
            $this->event($runState, EventType::RunStarted, [
                'base_commit' => $runState->runStart->baseCommit,
                'phases' => array_map(static fn (Phase $phase): string => $phase->value, (new Phases())->all()),
                'spec' => $runState->runStart->specPath,
                'ticket' => $runState->ticket->id,
            ]),
            $this->event($runState, EventType::PhaseBegan, ['phase' => Phase::Plan->value]),
        ]);
    }

    /**
     * @param RunState    $runState    The run, saved after the attempt.
     * @param PhaseReport $phaseReport The attempt.
     * @param Outcome     $outcome     What it did.
     *
     * @return list<string> Notes.
     */
    public function attempted(RunState $runState, PhaseReport $phaseReport, Outcome $outcome): array
    {
        $head = $this->json(['attempt' => $phaseReport->attempt, 'outcome' => $outcome->value, 'phase' => $phaseReport->phase->value]);
        $payload = substr($head, 0, -1) . ',"report":' . $this->json($phaseReport->toArray()) . '}';

        return self::kept([$this->eventLog->append($runState, EventType::PhaseAttempted, $payload)]);
    }

    /**
     * @param RunState   $runState The run, saved after it moved on.
     * @param Phase      $left     The phase it left.
     * @param Phase|null $began    The phase it began; none when it ended.
     *
     * @return list<string> Notes.
     */
    public function moved(RunState $runState, Phase $left, ?Phase $began): array
    {
        $notes = [$this->event($runState, EventType::PhaseEnded, ['phase' => $left->value])];

        if ($began instanceof Phase) {
            $notes[] = $this->event($runState, EventType::PhaseBegan, ['phase' => $began->value]);
        }

        return self::kept($notes);
    }

    /**
     * @param RunState     $runState     The run.
     * @param TicketStatus $ticketStatus The ticket's status from now.
     * @param string       $phase        Its phase line from now.
     *
     * @return array{RunState, list<string>} The run with the ticket as written, or with a note when the source
     *                                       refused; and the notes.
     */
    public function ticket(RunState $runState, TicketStatus $ticketStatus, string $phase): array
    {
        $ticket = $runState->ticket;

        try {
            $runState = $runState->withTicket($this->ticketSource->move($ticket, $ticketStatus, $phase));
            $note = '';
        } catch (WorkflowRefusal $workflowRefusal) {
            $note = 'ticket ' . $ticket->id . ' was not moved: ' . $workflowRefusal->getMessage();
            $runState = $runState->withNote(new RunNote($this->utcTime->now(), $phase, 'ticket', $note));
        }

        $logged = $this->event($runState, EventType::TicketMoved, [
            'note' => $note,
            'ok' => $note === '',
            'path' => $ticket->path,
            'phase' => $phase,
            'status' => $ticketStatus->value,
            'ticket' => $ticket->id,
        ]);

        return [$runState, self::kept([$note, $logged])];
    }

    /**
     * @param RunOutcome $runOutcome The completed run's outcome.
     *
     * @return list<string> Notes.
     */
    public function completed(RunOutcome $runOutcome): array
    {
        $payload = '{"outcome":' . $runOutcome->json() . '}';

        return self::kept([$this->eventLog->append($runOutcome->runState, EventType::RunCompleted, $payload)]);
    }

    /**
     * @param RunState $runState   The run, archived unfinished.
     * @param string   $archivedTo Where it went.
     *
     * @return list<string> Notes.
     */
    public function reset(RunState $runState, string $archivedTo): array
    {
        $currentPhase = $runState->currentPhase;

        return self::kept([$this->event($runState, EventType::RunReset, [
            'archived_run_id' => $runState->runStart->runId,
            'archived_to' => $archivedTo,
            'phase' => $currentPhase instanceof Phase ? $currentPhase->value : null,
        ])]);
    }

    /**
     * @param RunState                                $runState  The run.
     * @param EventType                               $eventType What happened.
     * @param array<string, scalar|list<string>|null> $payload   The payload.
     *
     * @return string Empty when the event was logged; a note otherwise.
     */
    private function event(RunState $runState, EventType $eventType, array $payload): string
    {
        return $this->eventLog->append($runState, $eventType, $this->json($payload));
    }

    /**
     * @param array<array-key, scalar|array<array-key, scalar|array<string, string>|null>|null> $value A payload or a report.
     *
     * @return string It as a JSON object.
     */
    private function json(array $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param list<string> $notes Notes, empty ones meaning nothing to note.
     *
     * @return list<string> The notes that say something.
     */
    private static function kept(array $notes): array
    {
        return array_values(array_filter($notes, static fn (string $note): bool => $note !== ''));
    }
}
