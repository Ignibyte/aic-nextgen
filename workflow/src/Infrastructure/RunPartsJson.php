<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use AIC\Workflow\Domain\CheckRow;
use AIC\Workflow\Domain\CheckStatus;
use AIC\Workflow\Domain\PhaseReport;
use AIC\Workflow\Domain\Phases;
use AIC\Workflow\Domain\RunNote;
use AIC\Workflow\Domain\Ticket;
use AIC\Workflow\Domain\TicketStatuses;
use AIC\Workflow\Exception\WorkflowRefusal;

/**
 * The parts of `run.json` that are objects of their own (#100): the ticket,
 * the attempts, the reports and the notes, each read strictly.
 */
final readonly class RunPartsJson
{
    /**
     * @param JsonReader $jsonReader The `ticket` object.
     *
     * @throws WorkflowRefusal When it is not a ticket's shape.
     *
     * @return Ticket The ticket.
     */
    public function ticket(JsonReader $jsonReader): Ticket
    {
        $jsonReader->only(['id', 'number', 'title', 'path', 'status']);
        $status = $jsonReader->string('status');

        return new Ticket(
            $jsonReader->string('id'),
            $jsonReader->int('number'),
            $jsonReader->string('title'),
            (new TicketStatuses())->named($status) ?? throw new WorkflowRefusal("the run's ticket has an unknown status."),
            $jsonReader->string('path'),
        );
    }

    /**
     * @param JsonReader $jsonReader The `attempts` object.
     *
     * @throws WorkflowRefusal When a phase is unknown or a count is not an integer.
     *
     * @return array<string, int> Attempts per phase.
     */
    public function attempts(JsonReader $jsonReader): array
    {
        $jsonReader->only(['plan', 'code', 'check', 'complete']);
        $attempts = [];

        foreach ($jsonReader->keys() as $phase) {
            $attempts[$phase] = $jsonReader->int($phase);
        }

        return $attempts;
    }

    /**
     * @param JsonReader $jsonReader The `reports` object.
     *
     * @throws WorkflowRefusal When a report is not a report's shape.
     *
     * @return array<string, PhaseReport> The last report per phase.
     */
    public function reports(JsonReader $jsonReader): array
    {
        $jsonReader->only(['plan', 'code', 'check', 'complete']);
        $reports = [];

        foreach ($jsonReader->keys() as $phase) {
            $reports[$phase] = $this->report($jsonReader->object($phase));
        }

        return $reports;
    }

    /**
     * @param list<JsonReader> $jsonReaders The `notes` list's objects.
     *
     * @throws WorkflowRefusal When a note is not a note's shape.
     *
     * @return list<RunNote> The notes.
     */
    public function notes(array $jsonReaders): array
    {
        $notes = [];

        foreach ($jsonReaders as $jsonReader) {
            $jsonReader->only(['at', 'phase', 'subject', 'note']);
            $notes[] = new RunNote(
                $jsonReader->string('at'),
                $jsonReader->string('phase'),
                $jsonReader->string('subject'),
                $jsonReader->string('note'),
            );
        }

        return $notes;
    }

    /**
     * @param JsonReader $jsonReader One report.
     *
     * @throws WorkflowRefusal When it is not a report's shape.
     *
     * @return PhaseReport The report.
     */
    private function report(JsonReader $jsonReader): PhaseReport
    {
        $jsonReader->only(['phase', 'attempt', 'advance', 'rows']);
        $name = $jsonReader->string('phase');
        $rows = [];

        foreach ($jsonReader->objects('rows') as $row) {
            $row->only(['check', 'status', 'why', 'remedy']);
            $status = match ($row->string('status')) {
                'passed' => CheckStatus::Passed,
                'blocked' => CheckStatus::Blocked,
                default => throw new WorkflowRefusal('a report row has an unknown status.'),
            };
            $rows[] = new CheckRow($row->string('check'), $status, $row->string('why'), $row->string('remedy'));
        }

        $phase = (new Phases())->named($name) ?? throw new WorkflowRefusal('a report names an unknown phase "' . $name . '".');

        return new PhaseReport($phase, $jsonReader->int('attempt'), $rows);
    }
}
