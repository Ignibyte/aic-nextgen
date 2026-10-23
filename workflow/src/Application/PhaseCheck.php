<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Workflow\Domain\CheckRow;
use AIC\Workflow\Domain\Phase;
use AIC\Workflow\Domain\RunState;
use AIC\Workflow\Exception\WorkflowRefusal;

/**
 * A check the engine runs at one phase (#100), its row holding the phase or
 * letting it pass. #101's gates and #103 to #106's checks implement it.
 */
interface PhaseCheck
{
    /**
     * @return Phase The phase it is due at.
     */
    public function phase(): Phase;

    /**
     * @param RunState $runState The run, at that phase.
     *
     * @throws WorkflowRefusal When a called workflow operation refuses its input or environment.
     *
     * @return CheckRow What it found.
     */
    public function check(RunState $runState): CheckRow;
}
