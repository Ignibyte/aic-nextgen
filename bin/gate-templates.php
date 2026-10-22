<?php

declare(strict_types=1);

use Gates\Templates\TemplateGrammarGate;

require_once __DIR__ . '/../vendor/autoload.php';

// GATE_TEMPLATES_ROOT points the gate at another root, for planted violations outside the repo (#96).
$root = getenv('GATE_TEMPLATES_ROOT');

exit((new TemplateGrammarGate())->run(is_string($root) && $root !== '' ? $root : dirname(__DIR__)));
