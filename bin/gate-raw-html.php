<?php

declare(strict_types=1);

use Gates\Rendering\RawHtmlGate;

require_once __DIR__ . '/../vendor/autoload.php';

// GATE_RAW_HTML_ROOT points the gate at another root, for planted violations outside the repo (#99).
$root = getenv('GATE_RAW_HTML_ROOT');

exit((new RawHtmlGate())->run(is_string($root) && $root !== '' ? $root : dirname(__DIR__)));
