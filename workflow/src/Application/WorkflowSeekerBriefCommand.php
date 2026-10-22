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
 * `bin/aic workflow:seeker-brief` (#103): the seeker's scope, as JSON, from
 * the engine. It writes nothing but the code index's refresh (#127).
 */
final readonly class WorkflowSeekerBriefCommand implements Command
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
     * @return CommandResponse The brief; exit 2 when refused.
     */
    #[Override]
    public function command(CommandInput $commandInput): CommandResponse
    {
        try {
            return new CommandResult($this->workflowContext->seekerBrief()->brief(), 0);
        } catch (WorkflowRefusal $workflowRefusal) {
            return new CommandResult(['workflow:seeker-brief — ' . $workflowRefusal->getMessage()], 2);
        }
    }
}
