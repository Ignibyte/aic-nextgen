<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain;

/**
 * A whole `composer run gate` as the engine read it (#101): the aggregate
 * verdict, why, and each gate's result.
 */
final readonly class GateRun
{
    /**
     * @param GateStatus       $gateStatus The aggregate.
     * @param string           $summary    Why: the runner's summary line, or what kept it from a verdict.
     * @param list<GateResult> $results    Each gate's result, in run order; empty when the run reached no verdict.
     */
    public function __construct(
        public GateStatus $gateStatus,
        public string $summary,
        public array $results,
    ) {
    }

    /**
     * @param GateStatus $gateStatus A status.
     *
     * @return list<string> The gates with it, by name.
     */
    public function named(GateStatus $gateStatus): array
    {
        $names = [];

        foreach ($this->results as $result) {
            if ($result->gateStatus !== $gateStatus) {
                continue;
            }

            $names[] = $result->name;
        }

        return $names;
    }
}
