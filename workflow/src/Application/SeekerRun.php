<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Workflow\Domain\Phase;
use AIC\Workflow\Domain\RunState;
use AIC\Workflow\Exception\WorkflowRefusal;

/**
 * The run the seeker reads (#103): open and at code, where its one pass is
 * (#127).
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
     * @throws WorkflowRefusal When no run is open, it is not at code, or it cannot be read.
     *
     * @return array{RunState, Phase} The open run and its phase.
     */
    public function open(): array
    {
        $runState = $this->runStore->load();
        $phase = $runState instanceof RunState
            ? $runState->currentPhase
            : null;

        if (!$runState instanceof RunState || !$phase instanceof Phase || $phase !== Phase::Code) {
            throw new WorkflowRefusal('the seeker reads an open run at code, once; this run is not at code.');
        }

        return [$runState, $phase];
    }
}
