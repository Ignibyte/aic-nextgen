<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Controller\Contracts\Command;
use AIC\Controller\Contracts\CommandInput;
use AIC\Controller\Contracts\CommandResponse;
use AIC\Controller\Domain\CommandResult;
use AIC\Workflow\Exception\WorkflowRefusal;
use InvalidArgumentException;
use Override;

/**
 * `bin/aic workflow:ask "<question>"` (#117), the agent's: puts one question
 * to the operator and holds the run until the operator answers from a
 * terminal. Stop is released while it waits; `workflow:run` is refused.
 */
final readonly class WorkflowAskCommand implements Command
{
    /**
     * @param WorkflowContext $workflowContext The workflow's parts.
     */
    public function __construct(
        private WorkflowContext $workflowContext,
    ) {
    }

    /**
     * @param CommandInput $commandInput The question, as one argument or as words.
     *
     * @throws InvalidArgumentException When a called boundary reports its documented failure.
     *
     * @return CommandResponse What to do next; exit 2 when refused.
     */
    #[Override]
    public function command(CommandInput $commandInput): CommandResponse
    {
        try {
            return new CommandResult($this->workflowContext->questionHold()->ask((new CommandWords())->from($commandInput, 0)), 0);
        } catch (WorkflowRefusal $workflowRefusal) {
            return new CommandResult(['workflow:ask — ' . $workflowRefusal->getMessage()], 2);
        }
    }
}
