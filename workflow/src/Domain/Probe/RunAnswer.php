<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain\Probe;

use function array_any;
use function array_map;
use function implode;
use function str_contains;

/**
 * What one `workflow:run` answered, as the sweep reads it (#119): the
 * outcome and its report's rows, each as `check`, `status` and the reason
 * with its remedy.
 */
final readonly class RunAnswer
{
    /**
     * @param string                                                   $outcome The call's outcome.
     * @param list<array{check: string, status: string, text: string}> $rows    Its report's rows.
     */
    public function __construct(
        public string $outcome,
        public array $rows,
    ) {
    }

    /**
     * @param Expect $expect A row it must hold.
     *
     * @return bool Whether one row has the check and the status, and its text holds the expected text and not the
     *              text it lacks.
     */
    public function holds(Expect $expect): bool
    {
        return array_any($this->rows, static fn (array $row): bool => $row['check'] === $expect->check
            && $row['status'] === $expect->status
            && str_contains($row['text'], $expect->contains)
            && ($expect->lacks === '' || !str_contains($row['text'], $expect->lacks)));
    }

    /**
     * @return string The outcome and each row's check and status, for a failing line.
     */
    public function summary(): string
    {
        $rows = array_map(static fn (array $row): string => $row['check'] . ' ' . $row['status'], $this->rows);

        return $this->outcome . ' [' . implode(', ', $rows) . ']';
    }
}
