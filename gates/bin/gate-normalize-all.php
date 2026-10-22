<?php

declare(strict_types=1);

use Gates\Application\NormalizeAllManifestsGate;
use Gates\Infrastructure\ProjectRoot;
use Gates\Infrastructure\SystemProcessRunner;

require __DIR__ . '/project.php';

$root = (new ProjectRoot())->path();

exit((new NormalizeAllManifestsGate(new SystemProcessRunner()))->run($root));
