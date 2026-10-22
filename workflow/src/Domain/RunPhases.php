<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain;

/**
 * The status of each of a run's four phases (#100), as `run.json` keeps them.
 */
final readonly class RunPhases
{
    /**
     * @param array<string, PhaseStatus> $statuses Each phase's status, by its name; a missing phase is pending.
     */
    public function __construct(
        private array $statuses,
    ) {
    }

    /**
     * @param Phase $phase A phase.
     *
     * @return PhaseStatus Its status.
     */
    public function status(Phase $phase): PhaseStatus
    {
        return $this->statuses[$phase->value] ?? PhaseStatus::Pending;
    }

    /**
     * @param Phase       $phase       A phase.
     * @param PhaseStatus $phaseStatus Its new status.
     *
     * @return self The phases with that one changed.
     */
    public function with(Phase $phase, PhaseStatus $phaseStatus): self
    {
        return new self([...$this->statuses, $phase->value => $phaseStatus]);
    }

    /**
     * @return array<string, string> Each phase's status by its name, in phase order.
     */
    public function names(): array
    {
        $names = [];

        foreach ((new Phases())->all() as $phase) {
            $names[$phase->value] = $this->status($phase)->value;
        }

        return $names;
    }
}
