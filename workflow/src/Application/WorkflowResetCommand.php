<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Controller\Contracts\Command;
use AIC\Controller\Contracts\CommandInput;
use AIC\Controller\Contracts\CommandResponse;
use AIC\Controller\Domain\CommandResult;
use AIC\Workflow\Exception\WorkflowRefusal;
use Override;

/**
 * `bin/aic workflow:reset` (#100): the operator archives the open run
 * unfinished, its ticket left as it is. Exit 2 from an agent's shell.
 */
final readonly class WorkflowResetCommand implements Command
{
    /**
     * @param WorkflowContext $workflowContext The workflow's parts.
     */
    public function __construct(
        private WorkflowContext $workflowContext,
    ) {
    }

    /**
     * @param CommandInput $commandInput None.
     *
     * @return CommandResponse What it did; exit 2 when refused.
     */
    #[Override]
    public function command(CommandInput $commandInput): CommandResponse
    {
        try {
            return new CommandResult($this->workflowContext->runReset()->reset(), 0);
        } catch (WorkflowRefusal $workflowRefusal) {
            return new CommandResult(['workflow:reset — ' . $workflowRefusal->getMessage()], 2);
        }
    }
}
