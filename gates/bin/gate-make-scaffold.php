<?php

declare(strict_types=1);

use AIC\Workflow\Infrastructure\MakeKernel;
use Gates\Application\MakeScaffoldGate;
use Gates\Application\ScaffoldFiles;
use Gates\Infrastructure\ProjectRoot;

require __DIR__ . '/project.php';

// gate:31 (#122): the make commands, over a project that hides modules/scaffold/ and keeps writes in memory.
$root = (new ProjectRoot())->path();
$scaffoldFiles = new ScaffoldFiles($root);
$commands = (new MakeKernel($root, $scaffoldFiles, $scaffoldFiles))->commands();

exit((new MakeScaffoldGate($scaffoldFiles, $commands))->run($root));
