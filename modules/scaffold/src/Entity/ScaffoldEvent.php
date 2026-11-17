<?php

declare(strict_types=1);

namespace App\Scaffold\Entity;

/**
 * One `scaffold_events` row, made by bin/aic make:entity (#123).
 */
final readonly class ScaffoldEvent
{
    /**
     * @param string $label      The label column.
     * @param string $state      The state column.
     * @param string $payload    The payload column.
     * @param string $happenedAt The happened_at column.
     * @param string $source     The source column.
     */
    public function __construct(
        public string $label,
        public string $state,
        public string $payload,
        public string $happenedAt,
        public string $source,
    ) {
    }

    /**
     * @return string The row in one line, for its list.
     */
    public function line(): string
    {
        return 'label: ' . $this->label
            . ', state: ' . $this->state
            . ', payload: ' . $this->payload
            . ', happened at: ' . $this->happenedAt
            . ', source: ' . $this->source;
    }
}
