<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain;

/**
 * One verdict as the evidence store keeps it (#101, Droost's `CheckRecord`
 * without baselines): which check, what it found, whose fault, why, and the
 * subject hash of the tree it judged.
 */
final readonly class CheckRecord
{
    /**
     * @param string     $name        What a criterion cites: `gate`, `gate:21`, `composition`, `rendered GET /path`.
     * @param CheckState $checkState  What it found.
     * @param Fault      $fault       Whose a fault is.
     * @param string     $summary     Why.
     * @param string     $subjectHash The tree it judged.
     * @param string     $transcript  Its output, capped; empty when it has none.
     */
    public function __construct(
        public string $name,
        public CheckState $checkState,
        public Fault $fault,
        public string $summary,
        public string $subjectHash,
        public string $transcript = '',
    ) {
    }

    /**
     * @param string $subjectHash The tree as it is now.
     *
     * @return bool Whether it is green and judged that tree.
     */
    public function greenFor(string $subjectHash): bool
    {
        return $this->checkState === CheckState::Satisfied && $this->subjectHash === $subjectHash;
    }
}
