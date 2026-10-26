<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make;

use AIC\Controller\Contracts\Command;
use AIC\Controller\Contracts\CommandInput;
use AIC\Controller\Contracts\CommandResponse;
use AIC\Workflow\Application\Make\Source\MigrationSource;
use AIC\Workflow\Domain\Console\CommandUsage;
use AIC\Workflow\Domain\Console\ParsedArguments;
use AIC\Workflow\Domain\Make\MadeFile;
use AIC\Workflow\Domain\Make\MakeRecipe;
use AIC\Workflow\Exception\WorkflowRefusal;
use InvalidArgumentException;
use Override;

/**
 * `bin/aic make:migration <module> <Class> <table> [--version=<v>]` (#122): a
 * migration creating its table with an id and a creation time. The version
 * is `--version`, or now in UTC and the class in snake case. The manifest
 * lists it.
 */
final readonly class MakeMigrationCommand implements Command
{
    /**
     * @param MakeRun         $makeRun         The run.
     * @param MakeVersions    $makeVersions    The version.
     * @param MakeNames       $makeNames       The rules.
     * @param MakeCalls       $makeCalls       The migration call.
     * @param MigrationSource $migrationSource Writes the migration.
     */
    public function __construct(
        private MakeRun $makeRun,
        private MakeVersions $makeVersions,
        private MakeNames $makeNames = new MakeNames(),
        private MakeCalls $makeCalls = new MakeCalls(),
        private MigrationSource $migrationSource = new MigrationSource(),
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
                'make:migration',
                'make:migration <module> <Class> <table> [--version=<v>] [--dry-run] [--json]',
                ['dry-run'],
                ['version'],
                3,
            ),
            $this->recipe(...),
        );
    }

    /**
     * @param ParsedArguments $parsedArguments The line.
     *
     * @throws WorkflowRefusal When a name breaks a rule.
     *
     * @return MakeRecipe The migration.
     */
    private function recipe(ParsedArguments $parsedArguments): MakeRecipe
    {
        [$module, $namespace, $class] = $this->makeNames->target('', $parsedArguments);
        $table = $this->makeNames->word($parsedArguments->positional(2), 'table');
        $version = $this->makeVersions->version($parsedArguments, $this->makeNames->snake($class));
        $source = $this->migrationSource->source($namespace, $class, $table, $version);
        $madeFile = new MadeFile('modules/' . $module . '/src/Migration/' . $class . '.php', $source);

        return new MakeRecipe($module, $class, [$madeFile], $this->makeCalls->classOnly('migration', 'Migration', $class, $namespace));
    }
}
