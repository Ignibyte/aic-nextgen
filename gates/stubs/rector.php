<?php

declare(strict_types=1);

// gate:7 — Rector (aic/gates' stub, #130): the settings are the package's base;
// this file names the app's paths.
$rector = require __DIR__ . '/vendor/aic/gates/config/rector.php';

return $rector(__DIR__, [
    'modules',
    'generated',
    'public',
    'bootstrap',
    'config',
    'bin',
]);
