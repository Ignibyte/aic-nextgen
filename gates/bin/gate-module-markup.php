<?php

declare(strict_types=1);

use Gates\Infrastructure\ProjectRoot;
use Gates\Markup\ModuleMarkupGate;

require __DIR__ . '/project.php';

$root = (new ProjectRoot())->path();

// GATE_MODULE_MARKUP_ROOT points the gate at another root, for planted violations outside the repo (#156).
$override = getenv('GATE_MODULE_MARKUP_ROOT');

exit((new ModuleMarkupGate())->run(is_string($override) && $override !== '' ? $override : $root));
