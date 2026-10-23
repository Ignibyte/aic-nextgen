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
 * `bin/aic workflow:seeker-report` (#103): files the ledger an `aic-seeker`
 * returned, piped in on standard input. Exit 0 when filed, 2 when refused;
 * it never advances the run.
 */
final readonly class WorkflowSeekerReportCommand implements Command
{
    /**
     * @param WorkflowContext $workflowContext The workflow's parts.
     */
    public function __construct(
        private WorkflowContext $workflowContext,
    ) {
    }

    /**
     * @param CommandInput $commandInput None: the ledger comes on standard input.
     *
     * @throws InvalidArgumentException When a called boundary reports its documented failure.
     *
     * @return CommandResponse What was filed; exit 2 when refused.
     */
    #[Override]
    public function command(CommandInput $commandInput): CommandResponse
    {
        if (!$commandInput->isEmpty()) {
            return new CommandResult(['workflow:seeker-report — it takes no argument: pipe the ledger in on standard input.'], 2);
        }

        try {
            return new CommandResult($this->workflowContext->seekerFiling()->file(), 0);
        } catch (WorkflowRefusal $workflowRefusal) {
            return new CommandResult(['workflow:seeker-report — ' . $workflowRefusal->getMessage()], 2);
        }
    }
}
