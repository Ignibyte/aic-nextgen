<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Controller\Contracts\Command;
use AIC\Controller\Contracts\CommandInput;
use AIC\Controller\Contracts\CommandResponse;
use AIC\Controller\Domain\CommandResult;
use AIC\Workflow\Domain\CodeAnswer;
use InvalidArgumentException;
use Override;

/**
 * `bin/aic code:search <text>` (#106): the symbols whose name contains the
 * text, case-insensitively, then the routes, commands and jobs that do.
 */
final readonly class CodeSearchCommand implements Command
{
    /**
     * @param CodeAsk  $codeAsk  Refreshes, answers and records.
     * @param CodeTree $codeTree Builds the answer.
     */
    public function __construct(
        private CodeAsk $codeAsk,
        private CodeTree $codeTree,
    ) {
    }

    /**
     * @param CommandInput $commandInput The text.
     *
     * @throws InvalidArgumentException When the response exit code is outside 0 to 255.
     *
     * @return CommandResponse The matches; exit 2 with no text.
     */
    #[Override]
    public function command(CommandInput $commandInput): CommandResponse
    {
        $text = $commandInput->argument(0, '');

        return $text === ''
            ? new CommandResult(['code:search — usage: bin/aic code:search <text>'], 2)
            : $this->codeAsk->ask('code:search', $text, fn (): CodeAnswer => $this->codeTree->search($text));
    }
}
