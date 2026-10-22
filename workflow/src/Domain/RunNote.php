<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain;

/**
 * Something a run records without failing on it (#100): a ticket move the
 * source refused, an event the log could not take.
 */
final readonly class RunNote
{
    /**
     * @param string $time    When, UTC `Y-m-d\TH:i:s\Z`.
     * @param string $phase   The phase the run was at; empty after it ended.
     * @param string $subject What it is about, such as `ticket`.
     * @param string $note    The note.
     */
    public function __construct(
        public string $time,
        public string $phase,
        public string $subject,
        public string $note,
    ) {
    }

    /**
     * @return array{at: string, phase: string, subject: string, note: string} Its `run.json` form.
     */
    public function toArray(): array
    {
        return ['at' => $this->time, 'phase' => $this->phase, 'subject' => $this->subject, 'note' => $this->note];
    }
}
