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

use function str_starts_with;

/**
 * `bin/aic workflow:ask "<question>"` (#117), the agent's: puts one question
 * to the operator and holds the run until the operator answers from a
 * terminal. Stop is released while it waits; `workflow:run` is refused. A
 * first word that is a flag asks nothing (#241): `--help` prints the usage,
 * any other is refused.
 */
final readonly class WorkflowAskCommand implements Command
{
    /**
     * How the command is called.
     */
    private const string USAGE = 'usage: bin/aic workflow:ask "<question>"';

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
        $first = $commandInput->argument(0, '');

        if (str_starts_with($first, '-')) {
            return self::flag($first);
        }

        try {
            return new CommandResult($this->workflowContext->questionHold()->ask((new CommandWords())->from($commandInput, 0)), 0);
        } catch (WorkflowRefusal $workflowRefusal) {
            return new CommandResult(['workflow:ask — ' . $workflowRefusal->getMessage()], 2);
        }
    }

    /**
     * @param string $flag The first word, a flag: the command takes none (#241).
     *
     * @throws InvalidArgumentException When the response exit code is outside 0 to 255.
     *
     * @return CommandResult The usage: exit 0 for `--help` or `-h`, exit 2 for any other flag, and no question recorded.
     */
    private static function flag(string $flag): CommandResult
    {
        $help = $flag === '--help' || $flag === '-h';
        $lines = $help
            ? [self::USAGE]
            : ['workflow:ask — it takes a question, not the flag ' . $flag . '; nothing was asked.', self::USAGE];

        return new CommandResult($lines, $help ? 0 : 2);
    }
}
