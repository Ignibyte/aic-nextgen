<?php

declare(strict_types=1);

use Gates\Application\PipelineSpecSyncGate;
use Gates\Infrastructure\ProjectRoot;

require __DIR__ . '/project.php';

$root = (new ProjectRoot())->path();

exit((new PipelineSpecSyncGate())->run($root));
