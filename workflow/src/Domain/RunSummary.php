<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain;

use AIC\Workflow\Domain\Guard\Hold;

use function array_map;
use function explode;
use function json_encode;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * `workflow:status`'s view of a run (#100): the open run, or the last one
 * archived, as the record says: its mode, what it waits on and every
 * answered question among it (#117).
 */
final readonly class RunSummary
{
    /**
     * @param RunState $runState The run.
     * @param bool     $open     Whether it is the open run, not an archived one.
     */
    public function __construct(
        private RunState $runState,
        private bool $open,
    ) {
    }

    /**
     * @return list<string> The summary, pretty JSON, one output line per JSON line.
     */
    public function lines(): array
    {
        $currentPhase = $this->runState->currentPhase;
        $awaiting = $this->runState->awaiting;
        $summary = [
            'attempts' => $this->runState->attempts,
            'awaiting' => $awaiting instanceof Hold ? $awaiting->toArray() : null,
            'current_phase' => $currentPhase instanceof Phase ? $currentPhase->value : null,
            'human_complete' => $this->runState->runStart->humanComplete,
            'mode' => $this->runState->runStart->mode->value,
            'notes' => array_map(static fn (RunNote $runNote): array => $runNote->toArray(), $this->runState->notes),
            'phases' => $this->runState->runPhases->names(),
            'qa_history' => $this->history(),
            'reports' => array_map(static fn (PhaseReport $phaseReport): array => $phaseReport->toArray(), $this->runState->reports),
            'run' => $this->open ? 'open' : 'archived',
            'run_id' => $this->runState->runStart->runId,
            'ticket' => $this->runState->ticket->toArray(),
        ];

        $flags = JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

        return explode("\n", json_encode($summary, $flags));
    }

    /**
     * @return list<array<string, string>> The answered questions, in order.
     */
    private function history(): array
    {
        return array_map(static fn (QuestionAnswer $questionAnswer): array => $questionAnswer->toArray(), $this->runState->qaHistory);
    }
}
