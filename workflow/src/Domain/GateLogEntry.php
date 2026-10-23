<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain;

/** A stored gate result with the identity and time of its actual adjudication. */
final readonly class GateLogEntry
{
    /**
     * @param int         $id                The immutable evidence record ID.
     * @param string      $runId             The run that recorded it.
     * @param Phase       $phase             The phase that recorded it.
     * @param int         $attempt           The phase attempt that recorded it.
     * @param string      $adjudicatedAt     The stored timestamp.
     * @param CheckRecord $checkRecord       Its outcome, subject and stored transcript.
     * @param bool        $transcriptPresent Whether a transcript row exists, including an observed empty stream.
     */
    public function __construct(
        public int $id,
        public string $runId,
        public Phase $phase,
        public int $attempt,
        public string $adjudicatedAt,
        public CheckRecord $checkRecord,
        public bool $transcriptPresent,
    ) {
    }
}
