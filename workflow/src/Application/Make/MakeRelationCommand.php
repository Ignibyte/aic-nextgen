<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make;

use AIC\Controller\Contracts\Command;
use AIC\Controller\Contracts\CommandInput;
use AIC\Controller\Contracts\CommandResponse;
use AIC\Workflow\Application\Console\ConsoleArguments;
use AIC\Workflow\Application\Make\Source\RelationSource;
use AIC\Workflow\Domain\Console\CommandUsage;
use AIC\Workflow\Domain\Console\ParsedArguments;
use AIC\Workflow\Domain\Make\MadeFile;
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
 * per pair and cascading foreign keys. The manifest lists it; the pivot's entity type and the relation on each side
 * are printed, for the agent to add to each table's entity type.
 */
final readonly class MakeRelationCommand implements Command
{
    /**
     * What `bin/aic make:relation` takes.
     */
    private const string USAGE = 'make:relation <module> <owner_table> <target_table> [--keys=int,int] [--fields=<name>:<type>,…]'
        . ' [--version=<v>] [--dry-run] [--json]';

    /**
     * @param MakeRun        $makeRun        The run.
     * @param MakeVersions   $makeVersions   The version.
     * @param MakeNames      $makeNames      The rules.
     * @param MakeFields     $makeFields     The pivot's fields.
     * @param MakeCalls      $makeCalls      The migration call.
     * @param RelationSource $relationSource Writes the migration and the lines.
     */
    public function __construct(
        private MakeRun $makeRun,
        private MakeVersions $makeVersions,
        private MakeNames $makeNames = new MakeNames(),
        private MakeFields $makeFields = new MakeFields(),
        private MakeCalls $makeCalls = new MakeCalls(),
        private RelationSource $relationSource = new RelationSource(),
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
     * @return MakeRecipe The pivot's migration, its manifest line, and the declarations to add.
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
        $source = $this->relationSource->migration($namespace, $class, $version, $tables, $uuid, $fields);
        $madeFile = new MadeFile('modules/' . $module . '/src/Migration/' . $class . '.php', $source);
        $manifestCall = $this->makeCalls->classOnly('migration', 'Migration', $class, $namespace);

        return new MakeRecipe($module, $class, [$madeFile], $manifestCall, lines: $this->relationSource->lines($tables, $uuid, $fields));
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
