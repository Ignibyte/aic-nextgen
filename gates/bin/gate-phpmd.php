<?php

declare(strict_types=1);

use Gates\Application\PhpmdGate;
use Gates\Infrastructure\ProjectRoot;
use Gates\Infrastructure\SystemProcessRunner;

require __DIR__ . '/project.php';

$root = (new ProjectRoot())->path();

exit((new PhpmdGate(new SystemProcessRunner()))->run($root, dirname(__DIR__), getenv()));
