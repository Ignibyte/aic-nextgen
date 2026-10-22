<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain;

use function array_all;
use function array_filter;
use function array_map;
use function array_values;
use function in_array;

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
     * @param string         $key     The progress key of the tree it judged (#117); empty in a report #100 to #106 wrote.
     */
    public function __construct(
        public Phase $phase,
        public int $attempt,
        public array $rows,
        public string $key = '',
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
     * @return list<CheckRow> The rows only the operator can clear.
     */
    public function held(): array
    {
        return array_values(array_filter(
            $this->rows,
            static fn (CheckRow $checkRow): bool => $checkRow->checkStatus === CheckStatus::Held,
        ));
    }

    /**
     * @return bool Whether every row that holds the phase is an inspection the seeker owes (#103).
     */
    public function inspectionOnly(): bool
    {
        return $this->only(CheckStatus::Due, [CheckStatus::Asked]);
    }

    /**
     * @return bool Whether every row that holds the phase waits for the operator's confirmation (#117).
     */
    public function askedOnly(): bool
    {
        return $this->only(CheckStatus::Asked, []);
    }

    /**
     * @return array{
     *     phase: string,
     *     attempt: int,
     *     advance: bool,
     *     key: string,
     *     rows: list<array{check: string, status: string, why: string, remedy: string}>
     * } Its `run.json` and event form.
     */
    public function toArray(): array
    {
        return [
            'advance' => $this->advance(),
            'attempt' => $this->attempt,
            'key' => $this->key,
            'phase' => $this->phase->value,
            'rows' => array_map(static fn (CheckRow $checkRow): array => $checkRow->toArray(), $this->rows),
        ];
    }

    /**
     * @param CheckStatus       $checkStatus The status every holding row must have.
     * @param list<CheckStatus> $ignored     Holding statuses left out of the test.
     *
     * @return bool Whether some row holds the phase and every holding row not ignored has the status.
     */
    private function only(CheckStatus $checkStatus, array $ignored): bool
    {
        $blocked = array_values(array_filter(
            $this->blocked(),
            static fn (CheckRow $checkRow): bool => !in_array($checkRow->checkStatus, $ignored, true),
        ));

        return $blocked !== [] && array_all($blocked, static fn (CheckRow $checkRow): bool => $checkRow->checkStatus === $checkStatus);
    }
}
