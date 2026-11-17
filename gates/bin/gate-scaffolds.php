<?php

declare(strict_types=1);

use AIC\Workflow\Application\Scaffold\ScaffoldLoader;
use AIC\Workflow\Infrastructure\LayeredScaffolds;
use AIC\Workflow\Infrastructure\LocalModuleFolders;
use AIC\Workflow\Infrastructure\LocalScaffolds;
use Gates\Application\ScaffoldCensus;
use Gates\Application\ScaffoldRenders;
use Gates\Application\ScaffoldsGate;
use Gates\Infrastructure\GatesPackage;
use Gates\Infrastructure\LocalProjectCopier;
use Gates\Infrastructure\ProjectRoot;
use Gates\Infrastructure\ScaffoldCacheFile;
use Gates\Infrastructure\SystemProcessRunner;

require __DIR__ . '/project.php';

// gate:33 (#300): the census on the planted fixture, then on the workflow package's scaffolds and the app's, then each
// kind that loads made and judged in a copy of the project. It needs ignibyte/aic-workflow, which holds the engine.
$root = (new ProjectRoot())->path();
$workflow = $root . '/vendor/ignibyte/aic-workflow';

if (!is_dir($workflow . '/src')) {
    echo 'gate:33 — ignibyte/aic-workflow is not installed, so there is no scaffold engine; deferring.', "\n";

    exit(0);
}

$fixtureFolder = (new GatesPackage())->path() . '/fixtures/scaffold-census';
$core = new LocalScaffolds($workflow . '/scaffolds', "the workflow package's scaffolds");
$app = new LocalScaffolds($root . '/scaffolds');
$fixture = new LocalScaffolds($fixtureFolder, 'the census fixture');
$scaffoldsGate = new ScaffoldsGate(
    new ScaffoldCensus(),
    new ScaffoldRenders(new SystemProcessRunner(), new LocalProjectCopier(), new ScaffoldCacheFile($root)),
    new ScaffoldLoader(new LayeredScaffolds($core, $app)),
    [$workflow . '/scaffolds' => $core, $root . '/scaffolds' => $app],
    [new ScaffoldLoader($fixture), $fixture],
    $fixtureFolder,
);

exit($scaffoldsGate->run($root, (new LocalModuleFolders($root))->names()));
