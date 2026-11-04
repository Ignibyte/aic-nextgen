<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use AIC\Controller\Contracts\Command;
use AIC\Workflow\Application\WorkflowAmendCommand;
use AIC\Workflow\Application\WorkflowAnswerCommand;
use AIC\Workflow\Application\WorkflowAskCommand;
use AIC\Workflow\Application\WorkflowContext;
use AIC\Workflow\Application\WorkflowResetCommand;
use AIC\Workflow\Application\WorkflowStatusCommand;

/**
 * The commands about what a run waits on (#117), kept apart so
 * `WorkflowKernel` stays under PHPMD's coupling cap: the agent's
 * `workflow:ask`, the operator's `workflow:answer`, `workflow:reset` and
 * `workflow:amend` (#267), and
 * `workflow:status`, each reaching the run through the workflow's context.
 */
final readonly class HoldKernel
{
    /**
     * @param WorkflowContext $workflowContext The workflow's parts.
     * @param string          $root            The project's root, for `workflow:amend`'s own parts (#267).
     */
    public function __construct(
        private WorkflowContext $workflowContext,
        private string $root,
    ) {
    }

    /**
     * @return array<string, Command> The five commands, by name.
     */
    public function commands(): array
    {
        return [
            'workflow:amend' => new WorkflowAmendCommand(new AmendParts($this->root)),
            'workflow:answer' => new WorkflowAnswerCommand($this->workflowContext),
            'workflow:ask' => new WorkflowAskCommand($this->workflowContext),
            'workflow:reset' => new WorkflowResetCommand($this->workflowContext),
            'workflow:status' => new WorkflowStatusCommand($this->workflowContext),
        ];
    }
}
