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
 * `bin/aic workflow:amend "<row>" "<new row>" "<reason>"` (#267): the operator replaces one row of
 * the open run's frozen spec, in the freeze and in the spec, with the reason on record. Exit 2 from
 * an agent's shell, and for anything that is not one row.
 */
final readonly class WorkflowAmendCommand implements Command
{
    /**
     * @param AmendContext $amendContext The amendment's parts.
     */
    public function __construct(
        private AmendContext $amendContext,
    ) {
    }

    /**
     * @param CommandInput $commandInput The frozen line, the new line and the reason.
     *
     * @throws InvalidArgumentException When a called boundary reports its documented failure.
     *
     * @return CommandResponse What it did; exit 2 when refused.
     */
    #[Override]
    public function command(CommandInput $commandInput): CommandResponse
    {
        try {
            $lines = $this->amendContext->specAmend()->amend(
                $commandInput->argument(0, ''),
                $commandInput->argument(1, ''),
                (new CommandWords())->from($commandInput, 2),
            );

            return new CommandResult($lines, 0);
        } catch (WorkflowRefusal $workflowRefusal) {
            return new CommandResult(['workflow:amend — ' . $workflowRefusal->getMessage()], 2);
        }
    }
}
