<?php

declare(strict_types=1);

use Gates\Application\ShellcheckGate;
use Gates\Infrastructure\ProjectRoot;
use Gates\Infrastructure\SystemProcessRunner;

require __DIR__ . '/project.php';

$root = (new ProjectRoot())->path();

exit((new ShellcheckGate(new SystemProcessRunner()))->run($root));
