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

use function array_key_exists;

/**
 * `bin/aic make:entity <module> <Class> <table> --fields=<name>:<type>,… [--version=<v>]` (#123): a reachable table
 * slice in the module: the entity, its repository, its migration and a list page of the newest rows. One manifest
 * edit routes the page and runs the migration; a lone entity would be unused. With `--existing` (#338) the table is one
 * a module migration creates already: its columns are read from that migration, and no migration is made.
 */
final readonly class MakeEntityCommand implements Command
{
    /**
     * @param MakeRun          $makeRun          The run.
     * @param FieldEntities    $fieldEntities    `--fields`: the table typed by the line, with its migration.
     * @param ExistingEntities $existingEntities `--existing`: a table a module migration creates already (#338).
     */
    public function __construct(
        private MakeRun $makeRun,
        private FieldEntities $fieldEntities,
        private ExistingEntities $existingEntities,
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
            new CommandUsage(
                'make:entity',
                'make:entity <module> <Class> <table> (--fields=<name>:<type>,… [--key=uuid] [--version=<v>] | --existing)'
                    . ' [--dry-run] [--json]',
                ['dry-run', 'existing'],
                ['fields', 'key', 'version'],
                3,
            ),
            $this->recipe(...),
        );
    }

    /**
     * @param ParsedArguments $parsedArguments The line.
     *
     * @throws WorkflowRefusal When a name, a field or the existing table breaks a rule.
     *
     * @return MakeRecipe The files and the route; with `--fields`, the migration too.
     */
    private function recipe(ParsedArguments $parsedArguments): MakeRecipe
    {
        return array_key_exists('existing', $parsedArguments->flags)
            ? $this->existingEntities->recipe($parsedArguments)
            : $this->fieldEntities->recipe($parsedArguments);
    }
}
