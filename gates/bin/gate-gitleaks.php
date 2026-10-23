<?php

declare(strict_types=1);

use Gates\Application\ExternalTool;
use Gates\Application\GitleaksGate;
use Gates\Infrastructure\GatesPackage;
use Gates\Infrastructure\ProjectRoot;
use Gates\Infrastructure\SystemProcessRunner;

require __DIR__ . '/project.php';

$root = (new ProjectRoot())->path();

$homeSetting = getenv('HOME');
$home = is_string($homeSetting)
    ? $homeSetting
    : '';

exit((new GitleaksGate(new ExternalTool(new SystemProcessRunner()), (new GatesPackage())->path(), $home))->run($root));
