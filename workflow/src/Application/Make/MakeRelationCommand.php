<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make;

use AIC\Controller\Contracts\Command;
use AIC\Controller\Contracts\CommandInput;
use AIC\Controller\Contracts\CommandResponse;
use AIC\Workflow\Application\Console\ConsoleArguments;
use AIC\Workflow\Domain\Console\CommandUsage;
use AIC\Workflow\Domain\Console\ParsedArguments;
use AIC\Workflow\Domain\Make\MakeRecipe;
use AIC\Workflow\Exception\WorkflowRefusal;
use InvalidArgumentException;
use Override;

use function explode;
use function str_replace;
use function ucwords;

/**
 * `bin/aic make:relation <module> <owner_table> <target_table> [--keys=int,int] [--fields=…] [--version=<v>]`
 * (#306): the migration of the pivot table `<owner>_<target>` that relates two tables many to many, with each
 * side's key (`--keys`: int or uuid, owner first), the pivot's own fields (as make:entity takes them), a link once
 * per pair and cascading foreign keys. The manifest lists it. When both tables have an accessor in the module's
 * `<Module>Types` (#328), the pivot's accessor and a ManyToMany on each side go in there; otherwise they are printed,
 * for the agent to add where the module declares its types.
 */
final readonly class MakeRelationCommand implements Command
{
    /**
     * What `bin/aic make:relation` takes.
     */
    private const string USAGE = 'make:relation <module> <owner_table> <target_table> [--keys=int,int] [--fields=<name>:<type>,…]'
        . ' [--version=<v>] [--dry-run] [--json]';

    /**
     * @param MakeRun       $makeRun       The run.
     * @param MakeVersions  $makeVersions  The version.
     * @param MakeRelations $makeRelations Edits the module's types, or prints the declarations.
     * @param MakeNames     $makeNames     The rules.
     * @param MakeFields    $makeFields    The pivot's fields.
     * @param MakeCalls     $makeCalls     The migration call.
     */
    public function __construct(
        private MakeRun $makeRun,
        private MakeVersions $makeVersions,
        private MakeRelations $makeRelations,
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
        $commandUsage = new CommandUsage('make:relation', self::USAGE, ['dry-run'], ['fields', 'keys', 'version'], 3);

        return $this->makeRun->run($commandInput, $commandUsage, $this->recipe(...));
    }

    /**
     * @param ParsedArguments $parsedArguments The line.
     *
     * @throws WorkflowRefusal When a name, a key type or a field breaks a rule.
     *
     * @return MakeRecipe The pivot's migration, its manifest line, and the types with the relation or the declarations to
     *                    add.
     */
    private function recipe(ParsedArguments $parsedArguments): MakeRecipe
    {
        $module = $parsedArguments->positional(0);
        $namespace = $this->makeNames->module($module);
        $owner = $this->makeNames->word($parsedArguments->positional(1), 'table');
        $target = $this->makeNames->word($parsedArguments->positional(2), 'table');
        $tables = ['owner' => $owner, 'pivot' => $owner . '_' . $target, 'target' => $target];
        $class = 'Create' . str_replace('_', '', ucwords($tables['pivot'], '_')) . 'Table';
        $fields = $parsedArguments->flag('fields') === ''
            ? []
            : $this->makeFields->parse($parsedArguments->flag('fields'));
        $uuid = $this->keys($parsedArguments->flag('keys'));
        $version = $this->makeVersions->version($parsedArguments, 'create_' . $tables['pivot'] . '_table');
        $files = $this->makeRelations->files([$module, $namespace, $class, $version], $tables, $uuid, $fields);
        $manifestCall = $this->makeCalls->classOnly('migration', 'Migration', $class, $namespace);
        $lines = $this->makeRelations->lines($files, $namespace, $tables, $uuid, $fields);

        return new MakeRecipe($module, $class, $files, $manifestCall, lines: $lines);
    }

    /**
     * @param string $keys What `--keys` gave: the owner's key type and the target's, each int or uuid.
     *
     * @throws WorkflowRefusal When it is anything else.
     *
     * @return array<string, bool> Whether each side's key is a uuid: `owner`, `target`.
     */
    private function keys(string $keys): array
    {
        $types = explode(',', $keys === '' ? 'int,int' : $keys);
        $owner = $types[0];
        $target = $types[1] ?? '';

        foreach ([$owner, $target] as $type) {
            if ($type !== 'int' && $type !== 'uuid') {
                $why = "--keys takes the owner's and the target's key types, each int or uuid: --keys=int,uuid.";

                throw new WorkflowRefusal($why, ConsoleArguments::USAGE);
            }
        }

        return ['owner' => $owner === 'uuid', 'target' => $target === 'uuid'];
    }
}
