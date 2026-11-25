<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use AIC\Workflow\Application\Import\ModuleTables;
use AIC\Workflow\Application\Make\MigratedTables;
use AIC\Workflow\Application\ProjectFiles;
use AIC\Workflow\Domain\Make\MigratedTable;
use AIC\Workflow\Exception\WorkflowRefusal;
use AIC\Workflow\Parsing\MigrationColumns;
use Override;

/**
 * The modules' tables through php-parser (#338): the migration that creates
 * one, found among those `build/surface.json` lists, read by
 * `MigrationColumns`, never run.
 */
final readonly class ParsedMigrations implements MigratedTables
{
    /**
     * @param ModuleTables     $moduleTables     Finds the migrations.
     * @param ProjectFiles     $projectFiles     Reads them.
     * @param MigrationColumns $migrationColumns Reads a table's columns.
     */
    public function __construct(
        private ModuleTables $moduleTables,
        private ProjectFiles $projectFiles,
        private MigrationColumns $migrationColumns = new MigrationColumns(),
    ) {
    }

    /**
     * @param string $table A table.
     *
     * @throws WorkflowRefusal When no module migration creates it, or its migration cannot be read.
     *
     * @return MigratedTable The table as its creating migration declares it.
     */
    #[Override]
    public function table(string $table): MigratedTable
    {
        $file = $this->moduleTables->creator($table);
        $source = $file === ''
            ? null
            : $this->projectFiles->read($file);

        if ($source === null) {
            throw new WorkflowRefusal('no module migration creates ' . $table . ': import it (schema:import) or make it first.');
        }

        return $this->migrationColumns->table($source, $file, $table);
    }

    /**
     * @param string $table A table.
     *
     * @throws WorkflowRefusal When build/surface.json is missing.
     *
     * @return list<string> The migrations that alter it, each relative to the project.
     */
    #[Override]
    public function alterers(string $table): array
    {
        return $this->moduleTables->alterers($table);
    }
}
