<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use AIC\Workflow\Application\Import\ModuleTables;
use AIC\Workflow\Application\Make\ExistingEntities;
use AIC\Workflow\Application\Make\FieldEntities;
use AIC\Workflow\Application\Make\MakeEntityCommand;
use AIC\Workflow\Application\Make\MakeRun;
use AIC\Workflow\Application\Make\MakeVersions;
use AIC\Workflow\Application\ProjectFiles;

/**
 * `make:entity` composed (#123, #338): its `--fields` recipe and its
 * `--existing` one, which reads a table's columns from the module migration
 * that creates it; apart from `IntegrationMakes` for PHPMD's coupling cap.
 */
final readonly class EntityMakes
{
    /**
     * @param MakeRun      $makeRun      The run every make shares.
     * @param MakeVersions $makeVersions The migration's version.
     * @param ModuleTables $moduleTables Finds the migration that creates a table.
     * @param ProjectFiles $projectFiles Reads it.
     *
     * @return MakeEntityCommand `make:entity`.
     */
    public function command(
        MakeRun $makeRun,
        MakeVersions $makeVersions,
        ModuleTables $moduleTables,
        ProjectFiles $projectFiles,
    ): MakeEntityCommand {
        return new MakeEntityCommand(
            $makeRun,
            new FieldEntities($makeVersions),
            new ExistingEntities(new ParsedMigrations($moduleTables, $projectFiles)),
        );
    }
}
