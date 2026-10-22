<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain;

use function array_map;
use function explode;
use function json_encode;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * What one `workflow:run` did, and the envelope it prints (#100): the outcome,
 * the run, the report, the rows that hold it and the notes.
 */
final readonly class RunOutcome
{
    /**
     * @param Outcome      $outcome     What the call did.
     * @param RunState     $runState    The run as it was saved.
     * @param PhaseReport  $phaseReport The attempt the call made.
     * @param list<string> $notes       What the call recorded without failing on it.
     */
    public function __construct(
        public Outcome $outcome,
        public RunState $runState,
        public PhaseReport $phaseReport,
        public array $notes,
    ) {
    }

    /**
     * @return string The envelope as one JSON object, for the `run.completed` event.
     */
    public function json(): string
    {
        return json_encode($this->envelope(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return list<string> The envelope, pretty JSON, one output line per JSON line.
     */
    public function lines(): array
    {
        $flags = JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

        return explode("\n", json_encode($this->envelope(), $flags));
    }

    /**
     * @return array{
     *     outcome: string,
     *     run_id: string,
     *     ticket: array{id: string, number: int, title: string, path: string, status: string},
     *     current_phase: string|null,
     *     phases: array<string, string>,
     *     report: array{
     *         phase: string,
     *         attempt: int,
     *         advance: bool,
     *         key: string,
     *         rows: list<array{check: string, status: string, why: string, remedy: string}>
     *     },
     *     blocked: list<array{check: string, status: string, why: string, remedy: string}>,
     *     notes: list<string>
     * } The envelope.
     */
    private function envelope(): array
    {
        $currentPhase = $this->runState->currentPhase;

        return [
            'blocked' => array_map(static fn (CheckRow $checkRow): array => $checkRow->toArray(), $this->phaseReport->blocked()),
            'current_phase' => $currentPhase instanceof Phase ? $currentPhase->value : null,
            'notes' => $this->notes,
            'outcome' => $this->outcome->value,
            'phases' => $this->runState->runPhases->names(),
            'report' => $this->phaseReport->toArray(),
            'run_id' => $this->runState->runStart->runId,
            'ticket' => $this->runState->ticket->toArray(),
        ];
    }
}
