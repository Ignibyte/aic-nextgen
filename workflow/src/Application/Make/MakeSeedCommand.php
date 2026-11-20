<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make;

use AIC\Controller\Contracts\Command;
use AIC\Controller\Contracts\CommandInput;
use AIC\Controller\Contracts\CommandResponse;
use AIC\Workflow\Domain\Console\CommandUsage;
use AIC\Workflow\Domain\Console\ParsedArguments;
use AIC\Workflow\Domain\Make\MakeRecipe;
use AIC\Workflow\Exception\WorkflowRefusal;
use InvalidArgumentException;
use Override;

/**
 * `bin/aic make:seed <module> <Name>Seed <table> --unique=<field> --fields=<name>:<type>,… [--dry-run] [--json]`
 * (#320): a command that saves fixed rows into a table through its own repository, updating them on a rerun. The
 * manifest names the command; `MakeSeeds` reads the line.
 */
final readonly class MakeSeedCommand implements Command
{
    /**
     * The usage.
     */
    private const string USAGE = 'make:seed <module> <Name>Seed <table> --unique=<field> --fields=<name>:<type>,… [--dry-run] [--json]';

    /**
     * @param MakeRun   $makeRun   The run.
     * @param MakeSeeds $makeSeeds Reads the line into the files.
     */
    public function __construct(
        private MakeRun $makeRun,
        private MakeSeeds $makeSeeds = new MakeSeeds(),
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
            new CommandUsage('make:seed', self::USAGE, ['dry-run'], ['fields', 'unique'], 3),
            $this->recipe(...),
        );
    }

    /**
     * @param ParsedArguments $parsedArguments The line.
     *
     * @throws WorkflowRefusal When a name, the table or a field breaks a rule.
     *
     * @return MakeRecipe The seed.
     */
    private function recipe(ParsedArguments $parsedArguments): MakeRecipe
    {
        return $this->makeSeeds->recipe($parsedArguments);
    }
}
