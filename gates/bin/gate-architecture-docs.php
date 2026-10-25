<?php

declare(strict_types=1);

use AIC\Workflow\Infrastructure\WorkflowKernel;
use Gates\Application\ArchitectureDocsGate;
use Gates\Infrastructure\ProjectRoot;

require __DIR__ . '/project.php';

$root = (new ProjectRoot())->path();

exit((new ArchitectureDocsGate(array_keys((new WorkflowKernel($root))->commands())))->run($root));
