<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use AIC\Controller\Contracts\Command;
use AIC\Workflow\Application\Make\MakeApiCommand;
use AIC\Workflow\Application\Make\MakeBlockCommand;
use AIC\Workflow\Application\Make\MakeCommandCommand;
use AIC\Workflow\Application\Make\MakeComponentCommand;
use AIC\Workflow\Application\Make\MakeJobCommand;
use AIC\Workflow\Application\Make\MakeMigrationCommand;
use AIC\Workflow\Application\Make\MakeModuleCommand;
use AIC\Workflow\Application\Make\MakePageCommand;
use AIC\Workflow\Application\Make\ModuleFolders;
use AIC\Workflow\Application\ProjectFiles;
use AIC\Workflow\Application\UtcTime;

/**
 * The make commands (#122), dev-only like the rest of the workflow:
 * `make:module`, `make:page`, `make:api`, `make:component`, `make:block`,
 * `make:command`, `make:job`, `make:migration` and `make:theme`, each writing files that
 * pass every gate and editing its module's manifest. `CodeKernel` merges them;
 * gate:31 builds them over its in-memory files.
 */
final readonly class MakeKernel
{
    /**
     * This package's root, which the guard's protected paths name.
     */
    private const string PACKAGE = __DIR__ . '/../..';

    /**
     * @param string             $root          The project's root.
     * @param ProjectFiles|null  $projectFiles  The files to read and write; the project's on disk when null.
     * @param ModuleFolders|null $moduleFolders The modules there; those on disk when null.
     * @param bool               $watched       Whether the guard watches the makes and an open run records them; gate:31
     *                                          makes over memory, so neither (#119).
     */
    public function __construct(
        private string $root,
        private ?ProjectFiles $projectFiles = null,
        private ?ModuleFolders $moduleFolders = null,
        private bool $watched = true,
    ) {
    }

    /**
     * @return array<string, Command> The nine commands, by name.
     */
    public function commands(): array
    {
        $moduleFolders = $this->moduleFolders ?? new LocalModuleFolders($this->root);
        $projectFiles = $this->projectFiles ?? new LocalProjectFiles($this->root);
        $makeParts = new MakeParts($this->root, $projectFiles, $moduleFolders, '', self::PACKAGE, $this->watched);
        $makeRun = $makeParts->makeRun();

        return [
            'make:api' => new MakeApiCommand($makeRun),
            'make:block' => new MakeBlockCommand($makeRun),
            'make:command' => new MakeCommandCommand($makeRun),
            'make:component' => new MakeComponentCommand($makeRun),
            'make:job' => new MakeJobCommand($makeRun),
            'make:migration' => new MakeMigrationCommand($makeRun, new UtcTime(new SystemClock())),
            'make:module' => new MakeModuleCommand($makeRun, $moduleFolders),
            'make:page' => new MakePageCommand($makeRun),
            'make:theme' => $makeParts->theme($makeRun),
        ];
    }
}
