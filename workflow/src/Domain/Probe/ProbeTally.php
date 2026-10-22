<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain\Probe;

use function array_filter;
use function count;

/**
 * The sweep's count (#119), Droost's tally made one over every expectation:
 * a failure and a step not run both count against it.
 */
final readonly class ProbeTally
{
    /**
     * @param int $passed The expectations that held.
     * @param int $failed Those that did not.
     * @param int $notRun Those never reached.
     */
    public function __construct(
        public int $passed = 0,
        public int $failed = 0,
        public int $notRun = 0,
    ) {
    }

    /**
     * @param list<ProbeLine> $lines More lines.
     *
     * @return self The tally with them counted.
     */
    public function with(array $lines): self
    {
        $count = static fn (ProbeVerdict $probeVerdict): int => count(
            array_filter($lines, static fn (ProbeLine $probeLine): bool => $probeLine->probeVerdict === $probeVerdict),
        );

        return new self(
            $this->passed + $count(ProbeVerdict::Pass),
            $this->failed + $count(ProbeVerdict::Fail),
            $this->notRun + $count(ProbeVerdict::NotRun),
        );
    }

    /**
     * @return string `probe-summary: P passed, F failed, N not run.`
     */
    public function summary(): string
    {
        return 'probe-summary: ' . $this->passed . ' passed, ' . $this->failed . ' failed, ' . $this->notRun . ' not run.';
    }

    /**
     * @return int 0 when every expectation held, else 1.
     */
    public function exit(): int
    {
        return $this->failed === 0 && $this->notRun === 0
            ? 0
            : 1;
    }
}
