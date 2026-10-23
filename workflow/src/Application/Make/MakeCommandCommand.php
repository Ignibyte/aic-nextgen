<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make;

use AIC\Controller\Contracts\Command;
use AIC\Controller\Contracts\CommandInput;
use AIC\Controller\Contracts\CommandResponse;
use AIC\Workflow\Application\Make\Source\CommandSource;
use AIC\Workflow\Domain\Console\CommandUsage;
use AIC\Workflow\Domain\Console\ParsedArguments;
use AIC\Workflow\Domain\Make\MakeRecipe;
use AIC\Workflow\Exception\WorkflowRefusal;
use InvalidArgumentException;
use Override;

/**
 * `bin/aic make:command <module> <Class> <name> [--dry-run] [--json]` (#122):
 * a command that prints one line. The manifest names it.
 */
final readonly class MakeCommandCommand implements Command
{
    /**
     * @param MakeRun       $makeRun       The run.
     * @param MakeNamed     $makeNamed     The recipe it shares with make:job.
     * @param CommandSource $commandSource Writes the command.
     */
    public function __construct(
        private MakeRun $makeRun,
        private MakeNamed $makeNamed = new MakeNamed(),
        private CommandSource $commandSource = new CommandSource(),
    ) {
    }

    /**
     * @param CommandInput $commandInput The line.
     *
     * @throws InvalidArgumentException When the response exit code is outside 0 to 255.
     *
     * @return CommandResponse What it wrote.
     */
    #[Override]
    public function command(CommandInput $commandInput): CommandResponse
    {
        return $this->makeRun->run(
            $commandInput,
            new CommandUsage('make:command', 'make:command <module> <Class> <name> [--dry-run] [--json]', ['dry-run'], [], 3),
            $this->recipe(...),
        );
    }

    /**
     * @param ParsedArguments $parsedArguments The line.
     *
     * @throws WorkflowRefusal When a name breaks a rule.
     *
     * @return MakeRecipe The command.
     */
    private function recipe(ParsedArguments $parsedArguments): MakeRecipe
    {
        return $this->makeNamed->recipe($parsedArguments, 'command', $this->commandSource->source(...));
    }
}
