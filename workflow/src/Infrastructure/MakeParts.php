<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use AIC\Controller\Contracts\Command;
use AIC\Workflow\Application\Make\MakeEntityCommand;
use AIC\Workflow\Application\Make\MakeMigrationCommand;
use AIC\Workflow\Application\Make\MakePlanner;
use AIC\Workflow\Application\Make\MakeReport;
use AIC\Workflow\Application\Make\MakeRun;
use AIC\Workflow\Application\Make\MakeVersions;
use AIC\Workflow\Application\Make\ModuleFolders;
use AIC\Workflow\Application\ProjectFiles;
use AIC\Workflow\Application\UtcTime;

use function array_keys;

/**
 * The make commands' run (#122), composed apart so `MakeKernel` stays under
 * PHPMD's coupling cap: the files, the manifests, the planner with the
 * surface and the workflow's own command names, the run's side
 * (`MakeRunParts`) and the report. gate:31 composes it over its in-memory
 * files.
 */
final readonly class MakeParts
{
    /**
     * @param string        $root          The project's root.
     * @param ProjectFiles  $projectFiles  The files the make reads and writes.
     * @param ModuleFolders $moduleFolders The modules there.
     * @param string        $home          The user's home, for the guard's protected paths; empty when unknown.
     * @param string        $package       The workflow package's directory, for the same.
     * @param bool          $watched       Whether the guard watches the makes and the run records them.
     */
    public function __construct(
        private string $root,
        private ProjectFiles $projectFiles,
        private ModuleFolders $moduleFolders,
        private string $home,
        private string $package,
        private bool $watched = true,
    ) {
    }

    /**
     * @return MakeRun The run every make goes through.
     */
    public function makeRun(): MakeRun
    {
        $parsedManifests = new ParsedManifests($this->projectFiles);
        $makeRunParts = new MakeRunParts($this->root, $this->home, $this->package, $this->watched);
        $makePlanner = new MakePlanner(
            $parsedManifests,
            $this->moduleFolders,
            $this->projectFiles,
            new SurfaceEntries($this->root, new LocalHost($this->root)),
            fn (): array => array_keys((new WorkflowKernel($this->root))->commands()),
        );

        return new MakeRun(
            $makePlanner,
            $parsedManifests,
            $this->projectFiles,
            $makeRunParts->scope(),
            $makeRunParts->recorder(),
            new MakeReport($this->projectFiles),
        );
    }

    /**
     * @param MakeRun $makeRun The run every make goes through.
     *
     * @return array<string, Command> `make:migration` and `make:entity` (#123), which version a migration by now in UTC.
     */
    public function migrations(MakeRun $makeRun): array
    {
        $makeVersions = new MakeVersions(new UtcTime(new SystemClock()));

        return [
            'make:entity' => new MakeEntityCommand($makeRun, $makeVersions),
            'make:migration' => new MakeMigrationCommand($makeRun, $makeVersions),
        ];
    }
}
