<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain\Guard;

use AIC\Workflow\Domain\Phase;
use AIC\Workflow\Domain\RunState;
use AIC\Workflow\Domain\WorkflowSettings;

/**
 * What the guard knows at a call (#102): the project's settings, the open
 * run if any, and why the run's state could not be read when it could not.
 * A guard that cannot read the state refuses, as Droost's does.
 */
final readonly class GuardScene
{
    /**
     * @param WorkflowSettings $workflowSettings The settings; hard levers when `config/workflow.php` would not load.
     * @param RunState|null    $runState         The open run; null when none is.
     * @param string           $problem          Why the state could not be read; empty when it was.
     */
    public function __construct(
        public WorkflowSettings $workflowSettings,
        public ?RunState $runState,
        public string $problem = '',
    ) {
    }

    /**
     * @return Phase|null The open run's current phase; null with no run, or one that ended.
     */
    public function phase(): ?Phase
    {
        return $this->runState instanceof RunState
            ? $this->runState->currentPhase
            : null;
    }

    /**
     * @return Enforcement The open run's frozen enforcement; the settings' when no run is open.
     */
    public function enforcement(): Enforcement
    {
        return $this->runState instanceof RunState
            ? $this->runState->runStart->enforcement
            : $this->workflowSettings->enforcement;
    }
}
