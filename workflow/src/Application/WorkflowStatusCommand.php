<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Controller\Contracts\Command;
use AIC\Controller\Contracts\CommandInput;
use AIC\Controller\Contracts\CommandResponse;
use AIC\Controller\Domain\CommandResult;
use AIC\Workflow\Domain\RunState;
use AIC\Workflow\Domain\RunSummary;
use AIC\Workflow\Exception\WorkflowRefusal;
use InvalidArgumentException;
use Override;

/**
 * `bin/aic workflow:status` (#100): the open run as the record says, or the
 * last one archived. It writes nothing.
 */
final readonly class WorkflowStatusCommand implements Command
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
     * @throws InvalidArgumentException When a called boundary reports its documented failure.
     *
     * @return CommandResponse The run's summary; exit 2 when refused.
     */
    #[Override]
    public function command(CommandInput $commandInput): CommandResponse
    {
        try {
            $runStore = $this->workflowContext->runStore();
            $open = $runStore->load();
            $runState = $open ?? $runStore->lastArchived();
        } catch (WorkflowRefusal $workflowRefusal) {
            return new CommandResult(['workflow:status — ' . $workflowRefusal->getMessage()], 2);
        }

        if (!$runState instanceof RunState) {
            return new CommandResult(['workflow:status — no run yet; begin one with bin/aic workflow:run <ticket>.'], 0);
        }

        return new CommandResult((new RunSummary($runState, $open instanceof RunState))->lines(), 0);
    }
}
