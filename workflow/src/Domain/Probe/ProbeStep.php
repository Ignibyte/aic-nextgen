<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain\Probe;

/**
 * One step of a probe scenario (#119): a plant, a command, a phase call, a
 * guard call. Each step holds one expectation or more; when a step fails,
 * the scenario ends and the expectations of the steps left count as not run.
 */
interface ProbeStep
{
    /**
     * @return string What the step does, for its lines.
     */
    public function label(): string;

    /**
     * @return int How many expectations it holds.
     */
    public function expectations(): int;
}
