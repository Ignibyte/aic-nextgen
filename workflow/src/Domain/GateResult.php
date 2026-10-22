<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain;

/**
 * One `gate:N`'s verdict from a gate run (#101): its status, a line saying
 * why, and its section of the run's output.
 */
final readonly class GateResult
{
    /**
     * @param string     $name       The gate, such as `gate:21`.
     * @param GateStatus $gateStatus Its verdict.
     * @param string     $summary    Why: its deferral line, or what failed.
     * @param string     $transcript Its section of the output, capped.
     */
    public function __construct(
        public string $name,
        public GateStatus $gateStatus,
        public string $summary,
        public string $transcript,
    ) {
    }
}
