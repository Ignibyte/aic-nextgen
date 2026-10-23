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

use function in_array;

/**
 * `bin/aic code:index [--full]` (#106): refreshes the code index, or builds
 * it whole, and prints what it did. Indexing is not a lookup, so nothing is
 * recorded for a run.
 */
final readonly class CodeIndexCommand implements Command
{
    /**
     * @param CodeIndex $codeIndex The index.
     */
    public function __construct(
        private CodeIndex $codeIndex,
    ) {
    }

    /**
     * @param CommandInput $commandInput `--full`, or nothing.
     *
     * @throws InvalidArgumentException When the response exit code is outside 0 to 255.
     *
     * @return CommandResponse The counts; exit 1 when it cannot build, 2 for another argument.
     */
    #[Override]
    public function command(CommandInput $commandInput): CommandResponse
    {
        $argument = $commandInput->argument(0, '');

        if (!in_array($argument, ['', '--full'], true)) {
            return new CommandResult(['code:index — usage: bin/aic code:index [--full]'], 2);
        }

        try {
            return new CommandResult($this->codeIndex->refresh($argument === '--full')->lines(), 0);
        } catch (WorkflowRefusal $workflowRefusal) {
            return new CommandResult(['code:index — ' . $workflowRefusal->getMessage()], 1);
        }
    }
}
