<?php

declare(strict_types=1);

use Gates\Application\ComposerAuditGate;
use Gates\Infrastructure\ProjectRoot;
use Gates\Infrastructure\SystemProcessRunner;

require __DIR__ . '/project.php';

$root = (new ProjectRoot())->path();

exit((new ComposerAuditGate(new SystemProcessRunner()))->run($root));
