<?php

declare(strict_types=1);

use Gates\Application\ComposerUnusedGate;
use Gates\Infrastructure\GatesPackage;
use Gates\Infrastructure\ProjectRoot;
use Gates\Infrastructure\SystemProcessRunner;

require __DIR__ . '/project.php';

$root = (new ProjectRoot())->path();

exit((new ComposerUnusedGate(new SystemProcessRunner(), (new GatesPackage())->path()))->run($root));
