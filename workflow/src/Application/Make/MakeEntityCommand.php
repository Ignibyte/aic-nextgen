<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make;

use AIC\Controller\Contracts\Command;
use AIC\Controller\Contracts\CommandInput;
use AIC\Controller\Contracts\CommandResponse;
use AIC\Workflow\Domain\Console\CommandUsage;
use AIC\Workflow\Domain\Console\ParsedArguments;
use AIC\Workflow\Domain\Make\EntityPlan;
use AIC\Workflow\Domain\Make\MakeRecipe;
use AIC\Workflow\Exception\WorkflowRefusal;
use InvalidArgumentException;
use Override;

/**
 * `bin/aic make:entity <module> <Class> <table> --fields=<name>:<type>,… [--version=<v>]` (#123): a reachable table
 * slice in the module: the entity, its repository, its migration and a list page of the newest rows. One manifest
 * edit routes the page and runs the migration; a lone entity would be unused.
 */
final readonly class MakeEntityCommand implements Command
{
    /**
     * @param MakeRun      $makeRun      The run.
     * @param MakeVersions $makeVersions The migration's version.
     * @param MakeEntities $makeEntities Writes the files.
     * @param MakeNames    $makeNames    The rules.
     * @param MakeFields   $makeFields   Reads `--fields`.
     * @param MakeCalls    $makeCalls    The route and migration calls.
     */
    public function __construct(
        private MakeRun $makeRun,
        private MakeVersions $makeVersions,
        private MakeEntities $makeEntities = new MakeEntities(),
        private MakeNames $makeNames = new MakeNames(),
        private MakeFields $makeFields = new MakeFields(),
        private MakeCalls $makeCalls = new MakeCalls(),
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
                'make:entity <module> <Class> <table> --fields=<name>:<type>,… [--version=<v>] [--dry-run] [--json]',
                ['dry-run'],
                ['fields', 'version'],
                3,
            ),
            $this->recipe(...),
        );
    }

    /**
     * @param ParsedArguments $parsedArguments The line.
     *
     * @throws WorkflowRefusal When a name or a field breaks a rule.
     *
     * @return MakeRecipe The four files, the route and the migration.
     */
    private function recipe(ParsedArguments $parsedArguments): MakeRecipe
    {
        $fields = $this->makeFields->parse($parsedArguments->flag('fields'));
        [$module, $namespace, $class] = $this->makeNames->target('', $parsedArguments);
        $table = $this->makeNames->word($parsedArguments->positional(2), 'table');
        $version = $this->makeVersions->version($parsedArguments, 'create_' . $table . '_table');
        $entityPlan = new EntityPlan($module, $namespace, $class, $table, $version, $fields);
        $path = $this->makeNames->path($entityPlan->path());
        $manifestCall = $this->makeCalls->both(
            $this->makeCalls->route('Get', $path, $class . 'ListPage', $namespace),
            $this->makeCalls->classOnly('migration', 'Migration', $entityPlan->migration(), $namespace),
        );

        return new MakeRecipe($module, $class, $this->makeEntities->files($entityPlan), $manifestCall, ['route GET ' . $path], true);
    }
}
