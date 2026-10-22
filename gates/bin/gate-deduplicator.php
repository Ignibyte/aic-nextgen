<?php

declare(strict_types=1);

use Gates\Application\DeduplicatorGate;
use Gates\Infrastructure\ProjectRoot;
use Gates\Infrastructure\SystemProcessRunner;

require __DIR__ . '/project.php';

$root = (new ProjectRoot())->path();

exit((new DeduplicatorGate(new SystemProcessRunner()))->run($root));
