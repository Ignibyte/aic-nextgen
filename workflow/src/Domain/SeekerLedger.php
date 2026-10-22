<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain;

use function array_filter;
use function array_values;
use function count;

/**
 * A seeker's ledger, parsed (#103): who read, the tree it read, its findings,
 * how many out-of-scope observations it made, and its section verbatim.
 */
final readonly class SeekerLedger
{
    /**
     * @param SeekerInspector     $seekerInspector Who read.
     * @param string              $subject         The subject hash its `Subject:` line names.
     * @param list<SeekerFinding> $findings        Its rows; empty for `(no findings)`.
     * @param int                 $observations    Its out-of-scope observations, counted and never held.
     * @param string              $section         The `## Seeker Inspection` section as filed.
     */
    public function __construct(
        public SeekerInspector $seekerInspector,
        public string $subject,
        public array $findings,
        public int $observations,
        public string $section,
    ) {
    }

    /**
     * @return list<SeekerFinding> The findings that hold the run.
     */
    public function holding(): array
    {
        return array_values(array_filter($this->findings, static fn (SeekerFinding $seekerFinding): bool => $seekerFinding->holding()));
    }

    /**
     * @param SeekerSeverity $seekerSeverity A severity.
     *
     * @return int How many open findings have it.
     */
    public function open(SeekerSeverity $seekerSeverity): int
    {
        return count(array_filter(
            $this->findings,
            static fn (SeekerFinding $seekerFinding): bool => $seekerFinding->seekerSeverity === $seekerSeverity
                && $seekerFinding->seekerStatus === SeekerStatus::Open,
        ));
    }
}
