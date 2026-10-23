<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Controller\Contracts\Command;
use AIC\Controller\Contracts\CommandInput;
use AIC\Controller\Contracts\CommandResponse;
use AIC\Controller\Domain\CommandResult;
use AIC\Workflow\Domain\CodeAnswer;
use Closure;
use InvalidArgumentException;
use Override;

/**
 * `bin/aic code:wiki [<module>|<Class>]` (#108): a code wiki page, rendered
 * live from the surface and the index rather than read from `docs/wiki/`, so
 * an agent mid-change never reads a page staler than its own edits. No
 * argument is the index; a class shows its module's page. The lookup is
 * recorded while a run is open.
 */
final readonly class CodeWikiCommand implements Command
{
    /**
     * @param CodeAsk                     $codeAsk Refreshes, answers and records.
     * @param Closure(string): CodeAnswer $render  Renders the page for a target, empty for the index.
     */
    public function __construct(
        private CodeAsk $codeAsk,
        private Closure $render,
    ) {
    }

    /**
     * @param CommandInput $commandInput A module or a class; nothing for the index.
     *
     * @throws InvalidArgumentException When the response exit code is outside 0 to 255.
     *
     * @return CommandResponse The page; exit 1 when nothing has that name, 2 for more than one argument.
     */
    #[Override]
    public function command(CommandInput $commandInput): CommandResponse
    {
        $target = $commandInput->argument(0, '');

        if ($commandInput->argument(1, '') !== '') {
            return new CommandResult(['code:wiki — usage: bin/aic code:wiki [<module>|<Class>]'], 2);
        }

        return $this->codeAsk->ask('code:wiki', $target, fn (): CodeAnswer => ($this->render)($target));
    }
}
