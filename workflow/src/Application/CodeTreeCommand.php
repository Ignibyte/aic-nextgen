<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Controller\Contracts\Command;
use AIC\Controller\Contracts\CommandInput;
use AIC\Controller\Contracts\CommandResponse;
use AIC\Controller\Domain\CommandResult;
use AIC\Workflow\Domain\CodeAnswer;
use Override;

/**
 * `bin/aic code:tree [<module|System|path>]` (#106): the overview of the
 * project's systems and modules, or one area's files and what they declare.
 */
final readonly class CodeTreeCommand implements Command
{
    /**
     * @param CodeAsk  $codeAsk  Refreshes, answers and records.
     * @param CodeTree $codeTree Builds the answers.
     */
    public function __construct(
        private CodeAsk $codeAsk,
        private CodeTree $codeTree,
    ) {
    }

    /**
     * @param CommandInput $commandInput A module, a system or a path; nothing for the overview.
     *
     * @return CommandResponse The answer; exit 1 when nothing has that name, 2 for more than one argument.
     */
    #[Override]
    public function command(CommandInput $commandInput): CommandResponse
    {
        $target = $commandInput->argument(0, '');

        if ($commandInput->argument(1, '') !== '') {
            return new CommandResult(['code:tree — usage: bin/aic code:tree [<module|System|path>]'], 2);
        }

        return $this->codeAsk->ask(
            'code:tree',
            $target,
            fn (): CodeAnswer => $target === '' ? $this->codeTree->overview() : $this->codeTree->area($target),
        );
    }
}
