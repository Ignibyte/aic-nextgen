<?php

declare(strict_types=1);

use Gates\Application\ModuleBoundariesGate;
use Gates\Application\ModuleTreeReader;
use Gates\Infrastructure\ProjectRoot;
use Gates\Infrastructure\SystemProcessRunner;

require __DIR__ . '/project.php';

$root = (new ProjectRoot())->path();

exit((new ModuleBoundariesGate(new ModuleTreeReader(new SystemProcessRunner())))->run($root));
