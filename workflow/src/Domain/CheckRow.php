<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain;

/**
 * One check's row in a phase report (#100): which check, what it found, why,
 * and what to do about it.
 */
final readonly class CheckRow
{
    /**
     * @param string      $check       The check's name, such as `spec`.
     * @param CheckStatus $checkStatus What it found.
     * @param string      $why         Why.
     * @param string      $remedy      What to do next; empty when it passed.
     */
    public function __construct(
        public string $check,
        public CheckStatus $checkStatus,
        public string $why,
        public string $remedy,
    ) {
    }

    /**
     * @return array{check: string, status: string, why: string, remedy: string} Its `run.json` and event form.
     */
    public function toArray(): array
    {
        return ['check' => $this->check, 'status' => $this->checkStatus->value, 'why' => $this->why, 'remedy' => $this->remedy];
    }
}
