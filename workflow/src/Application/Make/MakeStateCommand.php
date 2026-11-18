<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make;

use AIC\Controller\Contracts\Command;
use AIC\Controller\Contracts\CommandInput;
use AIC\Controller\Contracts\CommandResponse;
use AIC\Workflow\Domain\Console\CommandUsage;
use AIC\Workflow\Domain\Console\ParsedArguments;
use AIC\Workflow\Domain\Make\MakeRecipe;
use InvalidArgumentException;
use Override;

/**
 * `bin/aic make:state <module> <table> <field> --states=a,b,… --transitions=name:a|b>c[@permission],… [--version=<v>]`
 * (#308): a state machine on a table's field. It writes the backed enum of the states, the class that declares
 * the transitions and the migration that adds the field as a choice of the states, every row starting in the first;
 * the manifest lists the migration, and the lines to add the field and the machine to the entity type are printed.
 */
final readonly class MakeStateCommand implements Command
{
    /**
     * What `bin/aic make:state` takes.
     */
    private const string USAGE = 'make:state <module> <table> <field> --states=<a>,<b>,… --transitions=<name>:<a>|<b>><c>[@<permission>],…'
        . ' [--version=<v>] [--dry-run] [--json]';

    /**
     * @param MakeRun      $makeRun      The run.
     * @param MakeVersions $makeVersions The version.
     * @param MakeStates   $makeStates   Reads the line and makes the files.
     */
    public function __construct(
        private MakeRun $makeRun,
        private MakeVersions $makeVersions,
        private MakeStates $makeStates = new MakeStates(),
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
        $commandUsage = new CommandUsage('make:state', self::USAGE, ['dry-run'], ['states', 'transitions', 'version'], 3);

        return $this->makeRun->run(
            $commandInput,
            $commandUsage,
            fn (ParsedArguments $parsedArguments): MakeRecipe => $this->makeStates->recipe($parsedArguments, $this->makeVersions),
        );
    }
}
