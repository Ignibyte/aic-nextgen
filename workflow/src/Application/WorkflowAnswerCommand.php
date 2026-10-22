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
 * `bin/aic workflow:answer` (#117), the operator's, from a terminal only:
 * `advance|stay ["<note>"]` for a phase exit, `"<answer>"` for the agent's
 * question. An `advance` then works the phase through the engine, so every
 * check runs again before it moves on.
 */
final readonly class WorkflowAnswerCommand implements Command
{
    /**
     * @param WorkflowContext $workflowContext The workflow's parts.
     */
    public function __construct(
        private WorkflowContext $workflowContext,
    ) {
    }

    /**
     * @param CommandInput $commandInput The option and the note, or the answer.
     *
     * @return CommandResponse What was recorded, then the phase's outcome after an advance; exit 2 when refused.
     */
    #[Override]
    public function command(CommandInput $commandInput): CommandResponse
    {
        try {
            [$advance, $lines] = $this->workflowContext->questionHold()->answer(
                $commandInput->argument(0, ''),
                (new CommandWords())->from($commandInput, 1),
            );

            if (!$advance) {
                return new CommandResult($lines, 0);
            }

            $runOutcome = $this->workflowContext->engine()->run('');

            return new CommandResult([...$lines, ...$runOutcome->lines()], $runOutcome->outcome->exitCode());
        } catch (WorkflowRefusal $workflowRefusal) {
            return new CommandResult(['workflow:answer — ' . $workflowRefusal->getMessage()], 2);
        }
    }
}
