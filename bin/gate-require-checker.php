<?php

declare(strict_types=1);

use Gates\Application\RequireCheckerGate;

require_once __DIR__ . '/../vendor/autoload.php';

$override = getenv('GATE_REQUIRE_CHECKER_ROOT');
$root = is_string($override) && $override !== ''
    ? mb_rtrim($override, '/')
    : dirname(__DIR__);

exit((new RequireCheckerGate())->run($root));
