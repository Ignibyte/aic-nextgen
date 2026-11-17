<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use AIC\Controller\Contracts\Command;
use AIC\Workflow\Application\Make\MakeReport;
use AIC\Workflow\Application\Make\MakeRun;
use AIC\Workflow\Application\Make\ModuleFolders;
use AIC\Workflow\Application\ProjectFiles;
use AIC\Workflow\Application\Scaffold\MakeCatalogCommand;
use AIC\Workflow\Application\Scaffold\MakeKindCommand;
use AIC\Workflow\Application\Scaffold\MakeScaffoldCommand;
use AIC\Workflow\Application\Scaffold\ScaffoldCopy;
use AIC\Workflow\Application\Scaffold\ScaffoldLoader;
use AIC\Workflow\Application\Scaffold\ScaffoldRecipe;

/**
 * The app scaffolds' commands (#298), composed apart so `MakeKernel` stays
 * under PHPMD's coupling cap: `make`, through the run every make shares,
 * and `make:catalog`, over AIC's kinds and the app's (#301).
 */
final readonly class ScaffoldMakes
{
    /**
     * The workflow package's own kinds and the built-in makes' templates (#301).
     */
    private const string PACKAGE_SCAFFOLDS = __DIR__ . '/../../scaffolds';

    /**
     * @param string        $root          The project's root.
     * @param ProjectFiles  $projectFiles  The files the make reads and writes.
     * @param ModuleFolders $moduleFolders The modules there.
     */
    public function __construct(
        private string $root,
        private ProjectFiles $projectFiles,
        private ModuleFolders $moduleFolders,
    ) {
    }

    /**
     * @param MakeRun $makeRun The run every make goes through.
     *
     * @return array<string, Command> `make`, `make:catalog` and `make:scaffold` (#299).
     */
    public function commands(MakeRun $makeRun): array
    {
        $layeredScaffolds = new LayeredScaffolds(
            new LocalScaffolds(self::PACKAGE_SCAFFOLDS, "the workflow package's scaffolds"),
            new LocalScaffolds($this->root . '/scaffolds'),
        );
        $scaffoldLoader = new ScaffoldLoader($layeredScaffolds);
        $makeReport = new MakeReport($this->projectFiles);
        $scaffoldRecipe = new ScaffoldRecipe(new ParsedManifests($this->projectFiles), $this->moduleFolders);

        $scaffoldCopy = new ScaffoldCopy($scaffoldLoader, $layeredScaffolds);

        return [
            'make' => new MakeKindCommand($makeRun, $makeReport, $scaffoldLoader, $scaffoldRecipe),
            'make:catalog' => new MakeCatalogCommand($scaffoldLoader, $makeReport),
            'make:scaffold' => new MakeScaffoldCommand($makeRun, $scaffoldLoader, $scaffoldCopy, new RunStateFile($this->root)),
        ];
    }
}
