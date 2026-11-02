<?php

declare(strict_types=1);

namespace AIC\Profiler\Domain;

/**
 * Whether the profiler records requests (#229), from `config/profiler.php`
 * (AIC_PROFILER). Off, nothing of it is built and no request changes.
 */
final readonly class ProfilerConfig
{
    /**
     * @param bool $enabled Whether every dynamic request is recorded in `aic_profiles`.
     */
    public function __construct(
        public bool $enabled = false,
    ) {
    }
}
