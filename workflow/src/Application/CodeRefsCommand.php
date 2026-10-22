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
 * `bin/aic code:refs <Class|Class::method|short name>` (#106): what refers
 * to a symbol and what it refers to. A short name resolves to its one match,
 * or its candidates are listed.
 */
final readonly class CodeRefsCommand implements Command
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
     * @param CommandInput $commandInput The symbol.
     *
     * @return CommandResponse Its references; exit 1 when the index has no such symbol, 2 with no name.
     */
    #[Override]
    public function command(CommandInput $commandInput): CommandResponse
    {
        $name = $commandInput->argument(0, '');

        return $name === ''
            ? new CommandResult(['code:refs — usage: bin/aic code:refs <Class|Class::method|short name>'], 2)
            : $this->codeAsk->ask('code:refs', $name, fn (): CodeAnswer => $this->codeTree->refs($name));
    }
}
