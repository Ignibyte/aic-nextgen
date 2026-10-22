<?php

declare(strict_types=1);

use Gates\Application\DocTodosGate;
use Gates\Infrastructure\ProjectRoot;

require __DIR__ . '/project.php';

$root = (new ProjectRoot())->path();

exit((new DocTodosGate())->run($root));
