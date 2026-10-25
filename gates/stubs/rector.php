<?php

declare(strict_types=1);

use Gates\Infrastructure\RectorSetup;

// gate:7 — Rector (aic/gates' stub, #130): the settings are the package's base;
// this file names the app's paths.
return (new RectorSetup())->configure(__DIR__, [
    'modules',
    'themes',
    'generated',
    'public',
    'bootstrap',
    'config',
    'bin',
]);
