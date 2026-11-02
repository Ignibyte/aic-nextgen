<?php

declare(strict_types=1);

namespace AIC\Profiler\Domain;

use AIC\Component\Contracts\Component;

/**
 * Tessera's debug meta (#231, Tessera #36): spliced into a profiled page's
 * head, so Tessera marks each element a response patched.
 */
final readonly class ProfilerDebug implements Component
{
    /**
     * @param string $mode What Tessera does: `highlight` marks each patched element for a moment.
     */
    public function __construct(
        public string $mode = 'highlight',
    ) {
    }
}
