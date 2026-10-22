<?php

declare(strict_types=1);

use Gates\Infrastructure\ProjectRoot;
use Gates\Rendering\RawHtmlGate;

require __DIR__ . '/project.php';

$root = (new ProjectRoot())->path();

// GATE_RAW_HTML_ROOT points the gate at another root, for planted violations outside the repo (#99).
$override = getenv('GATE_RAW_HTML_ROOT');

exit((new RawHtmlGate())->run(is_string($override) && $override !== '' ? $override : $root));
