<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Import;

use AIC\Workflow\Application\Console\ConsoleArguments;
use AIC\Workflow\Application\Make\MakeNames;
use AIC\Workflow\Application\Make\MakeVersions;
use AIC\Workflow\Application\ProjectFiles;
use AIC\Workflow\Domain\Console\ParsedArguments;
use AIC\Workflow\Domain\Import\ImportedTable;
use AIC\Workflow\Domain\Make\MakeRecipe;
use AIC\Workflow\Exception\WorkflowRefusal;

use function array_filter;
use function array_map;
use function array_values;
use function count;
use function explode;
use function implode;
use function preg_match;
use function substr;

/**
 * What `bin/aic schema:import` makes of a dump (#318): one migration per chosen table, in foreign-key order with
 * consecutive versions, and the manifest's `->migration()` calls; the notes are printed after the files.
 */
final readonly class SchemaImports
{
    /**
     * @param ProjectFiles   $projectFiles   Reads the dump.
     * @param ModuleTables   $moduleTables   The tables the modules create.
     * @param MakeVersions   $makeVersions   The first version, when none is given.
     * @param MakeNames      $makeNames      The rules.
     * @param DumpStatements $dumpStatements Reads the dump.
     * @param ImportPlanner  $importPlanner  Chooses and orders the tables.
     * @param ImportFiles    $importFiles    Writes the migrations.
     */
    public function __construct(
        private ProjectFiles $projectFiles,
        private ModuleTables $moduleTables,
        private MakeVersions $makeVersions,
        private MakeNames $makeNames = new MakeNames(),
        private DumpStatements $dumpStatements = new DumpStatements(),
        private ImportPlanner $importPlanner = new ImportPlanner(),
        private ImportFiles $importFiles = new ImportFiles(),
    ) {
    }

    /**
     * @param ParsedArguments $parsedArguments The line.
     *
     * @throws WorkflowRefusal When the dump is missing, states what Schema cannot declare, or overfills the folder.
     *
     * @return MakeRecipe The migrations and the manifest calls.
     */
    public function recipe(ParsedArguments $parsedArguments): MakeRecipe
    {
        $module = $parsedArguments->flag('module');
        $namespace = $this->makeNames->module($module);
        $dumpFacts = $this->dumpStatements->read($this->dump($parsedArguments->positional(0)));
        $named = explode(',', $parsedArguments->flag('tables'));
        $selection = array_values(array_filter($named, static fn (string $table): bool => $table !== ''));
        $tables = $this->importPlanner->plan($dumpFacts, $selection, $this->moduleTables->created());

        if ($this->moduleTables->migrations($module) + count($tables) > 15) {
            throw new WorkflowRefusal(
                'modules/' . $module . "/src/Migration/ would hold more than gate:26's 15 files; "
                    . 'import fewer tables with --tables, or into another module.',
            );
        }

        [$files, $call] = $this->importFiles->files($tables, $module, $namespace, $this->stamp($parsedArguments));
        $names = array_map(static fn (ImportedTable $importedTable): string => $importedTable->name, $tables);

        return new MakeRecipe($module, implode(',', $names), $files, $call, lines: $dumpFacts->notes());
    }

    /**
     * @param string $path The dump, relative to the project.
     *
     * @throws WorkflowRefusal When it is missing.
     *
     * @return string Its text.
     */
    private function dump(string $path): string
    {
        return $this->projectFiles->read($path) ?? throw new WorkflowRefusal(
            'there is no dump at ' . $path . '; give the path of a pg_dump --schema-only within the project.',
            ConsoleArguments::USAGE,
        );
    }

    /**
     * @param ParsedArguments $parsedArguments The line.
     *
     * @throws WorkflowRefusal When `--version` is not `YYYY_MM_DD_HHMMSS`.
     *
     * @return string The first migration's stamp.
     */
    private function stamp(ParsedArguments $parsedArguments): string
    {
        $given = $parsedArguments->flag('version');

        if ($given === '') {
            return substr($this->makeVersions->version($parsedArguments, 'schema_import'), 0, 17);
        }

        return preg_match('/^\\d{4}_\\d{2}_\\d{2}_\\d{6}$/', $given) === 1
            ? $given
            : throw new WorkflowRefusal("--version is the first migration's stamp, YYYY_MM_DD_HHMMSS.", ConsoleArguments::USAGE);
    }
}
