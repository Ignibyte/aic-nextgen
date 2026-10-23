<?php

declare(strict_types=1);

use Gates\Application\PsalmGate;
use Gates\Infrastructure\ProjectRoot;
use Gates\Infrastructure\SystemProcessRunner;

require __DIR__ . '/project.php';

$root = (new ProjectRoot())->path();

exit((new PsalmGate(new SystemProcessRunner()))->run($root));
