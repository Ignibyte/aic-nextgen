<?php

declare(strict_types=1);

use Gates\Application\GateSuite;
use Gates\Application\SuiteComposer;
use Gates\Application\SuiteFilesGate;
use Gates\Application\SuiteStubs;
use Gates\Infrastructure\GatesPackage;
use Gates\Infrastructure\ProjectRoot;

require __DIR__ . '/project.php';

// gate:30 (#130): the tools' root configs are aic/gates' stubs, and composer.json carries the suite's lines.
$root = (new ProjectRoot())->path();
$package = (new GatesPackage())->path();

exit((new SuiteFilesGate(new SuiteStubs($package), new SuiteComposer(new GateSuite($root, $package))))->run($root));
