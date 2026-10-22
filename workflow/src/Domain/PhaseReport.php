<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain;

use function array_filter;
use function array_map;
use function array_values;

/**
 * One attempt at a phase (#100): every due check's row. The phase advances
 * when no row blocks it.
 */
final readonly class PhaseReport
{
    /**
     * @param Phase          $phase   The phase.
     * @param int            $attempt Which attempt at it, from 1.
     * @param list<CheckRow> $rows    Each due check's row, in registration order.
     */
    public function __construct(
        public Phase $phase,
        public int $attempt,
        public array $rows,
    ) {
    }

    /**
     * @return bool Whether no row blocks the phase.
     */
    public function advance(): bool
    {
        return $this->blocked() === [];
    }

    /**
     * @return list<CheckRow> The rows that hold the phase.
     */
    public function blocked(): array
    {
        return array_values(array_filter(
            $this->rows,
            static fn (CheckRow $checkRow): bool => $checkRow->checkStatus->holds(),
        ));
    }

    /**
     * @return array{
     *     phase: string,
     *     attempt: int,
     *     advance: bool,
     *     rows: list<array{check: string, status: string, why: string, remedy: string}>
     * } Its `run.json` and event form.
     */
    public function toArray(): array
    {
        return [
            'advance' => $this->advance(),
            'attempt' => $this->attempt,
            'phase' => $this->phase->value,
            'rows' => array_map(static fn (CheckRow $checkRow): array => $checkRow->toArray(), $this->rows),
        ];
    }
}
