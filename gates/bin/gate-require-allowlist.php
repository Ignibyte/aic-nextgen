<?php

declare(strict_types=1);

use Gates\Application\RequireAllowlistGate;
use Gates\Infrastructure\ProjectRoot;

require __DIR__ . '/project.php';

$root = (new ProjectRoot())->path();

exit((new RequireAllowlistGate())->run($root));
