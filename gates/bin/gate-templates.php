<?php

declare(strict_types=1);

use Gates\Infrastructure\ConfiguredTheme;
use Gates\Infrastructure\ProjectRoot;
use Gates\Templates\TemplateGrammarGate;

require __DIR__ . '/project.php';

$root = (new ProjectRoot())->path();

// GATE_TEMPLATES_ROOT points the gate at another root, for planted violations outside the repo (#96).
$override = getenv('GATE_TEMPLATES_ROOT');

$target = is_string($override) && $override !== ''
    ? $override
    : $root;

exit((new TemplateGrammarGate())->run($target, (new ConfiguredTheme())->name($target)));
