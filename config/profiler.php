<?php

declare(strict_types=1);

/*
 * The profiler (#229), from the environment: AIC_PROFILER=1 records every
 * dynamic request in aic_profiles and names it in X-AIC-Profile. Leave it unset
 * in production: a profile holds each statement's SQL and each page's path.
 */

use AIC\Profiler\Domain\ProfilerConfig;

return new ProfilerConfig(enabled: getenv('AIC_PROFILER') === '1');
