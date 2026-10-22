<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain\Probe;

use Override;

use function count;

/**
 * One `bin/aic workflow:run` in the app (#119). It holds when the call
 * answers the outcome, and each expected row is one more expectation: a row
 * of the call's report with that check and status whose reason holds the
 * text.
 */
final readonly class RunStep implements ProbeStep
{
    /**
     * @param string                $outcome The outcome it must answer, such as `advanced` or `blocked`.
     * @param list<Expect>          $rows    The rows its report must hold.
     * @param array<string, string> $env     Variables it runs with, such as `PGPORT` => `1`.
     * @param int                   $ticket  The ticket to begin a run on; 0 continues the open one.
     */
    public function __construct(
        public string $outcome,
        public array $rows = [],
        public array $env = [],
        public int $ticket = 0,
    ) {
    }

    /**
     * @return string What the step does.
     */
    #[Override]
    public function label(): string
    {
        return $this->ticket === 0
            ? 'workflow:run'
            : 'workflow:run ' . $this->ticket;
    }

    /**
     * @return int The outcome and each row.
     */
    #[Override]
    public function expectations(): int
    {
        return 1 + count($this->rows);
    }
}
