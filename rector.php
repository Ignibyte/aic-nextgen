<?php

declare(strict_types=1);

use Gates\Infrastructure\RectorSetup;

// gate:7 — Rector, AIC's own config (#116): the settings are ignibyte/aic-gates' base;
// this file names AIC's paths.
return (new RectorSetup())->configure(__DIR__, [
    'framework/src',
    'modules',
    'themes',
    'build/generated',
    'public',
    'bootstrap',
    'config',
    'bin',
    'gates',
    'workflow/src',
    'workflow/bin',
    'workflow/rules',
    'workflow/probes/sweep',
]);
