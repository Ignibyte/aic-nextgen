<?php

declare(strict_types=1);

// gate:7 — Rector, AIC's own config (#116): the settings are aic/gates' base;
// this file names AIC's paths.
$rector = require __DIR__ . '/vendor/aic/gates/config/rector.php';

return $rector(__DIR__, [
    'framework/src',
    'modules',
    'generated',
    'public',
    'bootstrap',
    'config',
    'bin',
    'gates',
    'workflow/src',
    'workflow/bin',
    'workflow/rules',
]);
