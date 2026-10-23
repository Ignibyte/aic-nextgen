<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain;

/** One gate record's selectors and an optional explicit output window. */
final readonly class GateLogQuery
{
    /**
     * @param string            $gate      A gate:N name or the aggregate gate.
     * @param string            $runId     An explicit run; empty selects the open or latest archived run.
     * @param Phase|null        $phase     Restrict to this phase; null selects the latest matching phase.
     * @param int|null          $attempt   Restrict to this attempt; null selects the latest matching attempt.
     * @param LogLineRange|null $lineRange An explicit window; null keeps the complete stored transcript.
     * @param bool              $json      Whether the command returns a JSON envelope.
     */
    public function __construct(
        public string $gate,
        public string $runId,
        public ?Phase $phase,
        public ?int $attempt,
        public ?LogLineRange $lineRange,
        public bool $json,
    ) {
    }
}
