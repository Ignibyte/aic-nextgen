<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Workflow\Domain\RuleChange;
use AIC\Workflow\Domain\RunState;
use AIC\Workflow\Exception\WorkflowRefusal;

/**
 * What a run changed, as files a house rule can read (#104): each changed
 * path but the engine's own writes, its base content written out from the
 * base commit, so a rule runs no git.
 */
interface RuleChanges
{
    /**
     * @param RunState $runState The run.
     *
     * @throws WorkflowRefusal When the changes cannot be listed or the base copies written.
     *
     * @return list<RuleChange> The changes, sorted by path.
     */
    public function changes(RunState $runState): array;
}
