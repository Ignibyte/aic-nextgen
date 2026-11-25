<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use AIC\Controller\Contracts\Command;
use AIC\Workflow\Application\Import\ModuleTables;
use AIC\Workflow\Application\Import\SchemaImportCommand;
use AIC\Workflow\Application\Import\SchemaImports;
use AIC\Workflow\Application\Make\MakeClientCommand;
use AIC\Workflow\Application\Make\MakeMcpToolCommand;
use AIC\Workflow\Application\Make\MakeRun;
use AIC\Workflow\Application\Make\MakeVersions;
use AIC\Workflow\Application\ProjectFiles;
use AIC\Workflow\Application\UtcTime;

/**
 * The makes for what an app exchanges with other systems: `make:client`, a
 * client of an upstream API (#305), `make:mcp-tool`, a tool an agent calls
 * over MCP (#316), `schema:import`, the migrations of an existing
 * database's schema (#318), and `make:entity` (#123), here since it reads a
 * table's columns from the migration that creates it (#338).
 */
final readonly class IntegrationMakes
{
    /**
     * @param string       $root         The project's root.
     * @param ProjectFiles $projectFiles The files the makes read and write.
     */
    public function __construct(
        private string $root,
        private ProjectFiles $projectFiles,
    ) {
    }

    /**
     * @param MakeRun $makeRun The run every make shares.
     *
     * @return array<string, Command> Each make, by its command name.
     */
    public function commands(MakeRun $makeRun): array
    {
        $moduleTables = new ModuleTables(new SurfaceEntries($this->root, new LocalHost($this->root)), $this->projectFiles);
        $makeVersions = new MakeVersions(new UtcTime(new SystemClock()));

        return [
            'make:client' => new MakeClientCommand($makeRun),
            'make:entity' => (new EntityMakes())->command($makeRun, $makeVersions, $moduleTables, $this->projectFiles),
            'make:mcp-tool' => new MakeMcpToolCommand($makeRun),
            'schema:import' => new SchemaImportCommand($makeRun, new SchemaImports($this->projectFiles, $moduleTables, $makeVersions)),
        ];
    }
}
