<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Workflow\Domain\Phase;
use AIC\Workflow\Domain\RunState;
use AIC\Workflow\Exception\WorkflowRefusal;

/**
 * The run the seeker reads (#103): open, and past plan, where there is code
 * to read.
 */
final readonly class SeekerRun
{
    /**
     * @param RunStore $runStore The open run.
     */
    public function __construct(
        private RunStore $runStore,
    ) {
    }

    /**
     * @throws WorkflowRefusal When no run is open, it is at plan, or it cannot be read.
     *
     * @return array{RunState, Phase} The open run and its phase.
     */
    public function open(): array
    {
        $runState = $this->runStore->load();
        $phase = $runState instanceof RunState
            ? $runState->currentPhase
            : null;

        if (!$runState instanceof RunState || !$phase instanceof Phase || $phase === Phase::Plan) {
            throw new WorkflowRefusal('the seeker reads an open run at code, check or complete; plan has no code to read.');
        }

        return [$runState, $phase];
    }
}
