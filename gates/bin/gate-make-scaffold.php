<?php

declare(strict_types=1);

use AIC\Workflow\Infrastructure\MakeKernel;
use Gates\Application\MakeScaffoldGate;
use Gates\Application\ScaffoldFiles;
use Gates\Infrastructure\ProjectRoot;

require __DIR__ . '/project.php';

// gate:31 (#122): the make commands, over a project that hides modules/scaffold/ and keeps writes in memory,
// unwatched: the guard does not judge them and an open run does not record them (#119).
$root = (new ProjectRoot())->path();
$scaffoldFiles = new ScaffoldFiles($root);
$commands = (new MakeKernel($root, $scaffoldFiles, $scaffoldFiles, false))->commands();

exit((new MakeScaffoldGate($scaffoldFiles, $commands))->run($root));
