<?php

declare(strict_types=1);

use Gates\Application\ExternalTool;
use Gates\Application\GitleaksGate;
use Gates\Infrastructure\SystemProcessRunner;

require_once __DIR__ . '/../vendor/autoload.php';

$home = is_string($_SERVER['HOME'] ?? null)
    ? $_SERVER['HOME']
    : '';

exit((new GitleaksGate(new ExternalTool(new SystemProcessRunner()), $home))->run(dirname(__DIR__)));
