<?php

declare(strict_types=1);

use Gates\Application\CaptainhookGate;
use Gates\Infrastructure\ProjectRoot;
use Gates\Infrastructure\SystemProcessRunner;
use Gates\Infrastructure\WorktreeRoot;

require __DIR__ . '/project.php';

$root = (new ProjectRoot())->path();

// A worktree shares the canonical checkout's installed hooks.
$gateRoot = (new WorktreeRoot())->path($root);

exit((new CaptainhookGate(new SystemProcessRunner()))->run($gateRoot));
