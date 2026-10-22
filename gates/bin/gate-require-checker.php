<?php

declare(strict_types=1);

use Gates\Application\RequireCheckerGate;
use Gates\Infrastructure\ProjectRoot;

require __DIR__ . '/project.php';

$root = (new ProjectRoot())->path();

$override = getenv('GATE_REQUIRE_CHECKER_ROOT');
$root = is_string($override) && $override !== ''
    ? mb_rtrim($override, '/')
    : $root;

exit((new RequireCheckerGate())->run($root));
