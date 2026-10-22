<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use AIC\Controller\Contracts\Command;
use AIC\Workflow\Application\WorkflowAnswerCommand;
use AIC\Workflow\Application\WorkflowAskCommand;
use AIC\Workflow\Application\WorkflowContext;
use AIC\Workflow\Application\WorkflowResetCommand;
use AIC\Workflow\Application\WorkflowStatusCommand;

/**
 * The commands about what a run waits on (#117), kept apart so
 * `WorkflowKernel` stays under PHPMD's coupling cap: the agent's
 * `workflow:ask`, the operator's `workflow:answer` and `workflow:reset`, and
 * `workflow:status`, each reaching the run through the workflow's context.
 */
final readonly class HoldKernel
{
    /**
     * @param WorkflowContext $workflowContext The workflow's parts.
     */
    public function __construct(
        private WorkflowContext $workflowContext,
    ) {
    }

    /**
     * @return array<string, Command> The four commands, by name.
     */
    public function commands(): array
    {
        return [
            'workflow:answer' => new WorkflowAnswerCommand($this->workflowContext),
            'workflow:ask' => new WorkflowAskCommand($this->workflowContext),
            'workflow:reset' => new WorkflowResetCommand($this->workflowContext),
            'workflow:status' => new WorkflowStatusCommand($this->workflowContext),
        ];
    }
}
