<?php

declare(strict_types=1);

namespace App\Note\Entity;

use DateTimeImmutable;

/**
 * One `note_marks` row, made by bin/aic make:entity (#123): the fixture's
 * uuid-keyed entity with a field of every type (#307).
 */
final readonly class NoteMark
{
    /**
     * @param string            $label    The label column.
     * @param string            $kind     The kind column.
     * @param string            $meta     The meta column.
     * @param DateTimeImmutable $markedAt The marked_at column, read as a point in time (#307).
     * @param string            $origin   The origin column.
     * @param string            $weight   The weight column.
     */
    public function __construct(
        public string $label,
        public string $kind,
        public string $meta,
        public DateTimeImmutable $markedAt,
        public string $origin,
        public string $weight,
    ) {
    }

    /**
     * @return string The row in one line, for its list.
     */
    public function line(): string
    {
        return 'label: ' . $this->label
            . ', kind: ' . $this->kind
            . ', meta: ' . $this->meta
            . ', marked at: ' . $this->markedAt->format('Y-m-d\\TH:i:sP')
            . ', origin: ' . $this->origin
            . ', weight: ' . $this->weight;
    }
}
