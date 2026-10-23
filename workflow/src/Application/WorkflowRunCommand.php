<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Controller\Contracts\Command;
use AIC\Controller\Contracts\CommandInput;
use AIC\Controller\Contracts\CommandResponse;
use AIC\Controller\Domain\CommandResult;
use AIC\Workflow\Domain\RegexCaptures;
use AIC\Workflow\Exception\WorkflowRefusal;
use LogicException;
use Override;

use function array_key_exists;

/**
 * `bin/aic workflow:run [<ticket>]` (#100): begins a run on the ticket when
 * none is open, then works the open run's current phase once. Exit 0 when it
 * advanced or completed, 1 when a check holds the phase, 2 when refused.
 */
final readonly class WorkflowRunCommand implements Command
{
    use RegexCaptures;

    /**
     * @param WorkflowContext $workflowContext The workflow's parts.
     */
    public function __construct(
        private WorkflowContext $workflowContext,
    ) {
    }

    /**
     * @param CommandInput $commandInput The ticket, optionally: `100`, `#100` or `0100`.
     *
     * @throws LogicException When the phase invariant or the response arguments are invalid.
     *
     * @return CommandResponse The envelope, or the refusal.
     */
    #[Override]
    public function command(CommandInput $commandInput): CommandResponse
    {
        try {
            $runOutcome = $this->workflowContext->engine()->run(self::ticketId($commandInput->argument(0, '')));
        } catch (WorkflowRefusal $workflowRefusal) {
            return new CommandResult(['workflow:run — ' . $workflowRefusal->getMessage()], 2);
        }

        return new CommandResult($runOutcome->lines(), $runOutcome->outcome->exitCode());
    }

    /**
     * @param string $argument The ticket as given; empty for none.
     *
     * @throws WorkflowRefusal When it is not a ticket number.
     *
     * @return string The ticket's id, its number without padding; empty for none.
     */
    private static function ticketId(string $argument): string
    {
        if ($argument === '') {
            return '';
        }

        $match = self::regexMatch('/^#?0*([1-9][0-9]{0,8})$/', $argument);

        if (!array_key_exists(1, $match)) {
            throw new WorkflowRefusal('"' . $argument . '" is not a ticket: name it by number, as 100, #100 or 0100.');
        }

        return $match[1];
    }
}
