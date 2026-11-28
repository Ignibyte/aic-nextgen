<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make;

use AIC\Workflow\Application\Console\ConsoleArguments;
use AIC\Workflow\Domain\Console\ParsedArguments;
use AIC\Workflow\Domain\Make\MakeRecipe;
use AIC\Workflow\Exception\WorkflowRefusal;

/**
 * `make:entity --existing` (#338): the entity, its repository and its list
 * page over a table a module migration creates already, its fields read from
 * that migration; no migration is made. A migration that alters the table
 * after is named in a note.
 */
final readonly class ExistingEntities
{
    /**
     * @param MigratedTables $migratedTables The tables the migrations create.
     * @param MakeEntities   $makeEntities   Writes the files.
     * @param ExistingFields $existingFields An existing table's fields.
     * @param MakeNames      $makeNames      The rules.
     * @param MakeCalls      $makeCalls      The route call.
     */
    public function __construct(
        private MigratedTables $migratedTables,
        private MakeEntities $makeEntities,
        private ExistingFields $existingFields = new ExistingFields(),
        private MakeNames $makeNames = new MakeNames(),
        private MakeCalls $makeCalls = new MakeCalls(),
    ) {
    }

    /**
     * @param ParsedArguments $parsedArguments The line, with `--existing`.
     *
     * @throws WorkflowRefusal When it names `--fields`, `--key` or `--version`, no migration creates the table, or a
     *                         column has no field.
     *
     * @return MakeRecipe The three files and the route.
     */
    public function recipe(ParsedArguments $parsedArguments): MakeRecipe
    {
        foreach (['fields', 'key', 'version'] as $flag) {
            if ($parsedArguments->flag($flag) !== '') {
                throw new WorkflowRefusal(
                    "--existing reads the columns from the table's migration; it takes no --" . $flag . '.',
                    ConsoleArguments::USAGE,
                );
            }
        }

        [$module, $namespace, $class] = $this->makeNames->target('', $parsedArguments);
        $migratedTable = $this->migratedTables->table($this->makeNames->word($parsedArguments->positional(2), 'table'));
        $entityPlan = $this->existingFields->plan($migratedTable, $module, $namespace, $class);
        $path = $this->makeNames->path($entityPlan->path());
        $lines = ['The columns of ' . $migratedTable->table . ' are read from ' . $migratedTable->file . '.'];

        foreach ($this->migratedTables->alterers($migratedTable->table) as $file) {
            $lines[] = $file . ' alters ' . $migratedTable->table . ', which make:entity does not read: add what it changes by hand.';
        }

        $manifestCall = $this->makeCalls->route('Get', $path, $class . 'ListPage', $namespace);

        $files = $this->makeEntities->files($entityPlan);

        return new MakeRecipe($module, $class, $files, $manifestCall, ['route GET ' . $path], true, lines: $lines);
    }
}
