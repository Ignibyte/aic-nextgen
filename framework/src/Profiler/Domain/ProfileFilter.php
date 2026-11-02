<?php

declare(strict_types=1);

namespace AIC\Profiler\Domain;

/**
 * What the profiler's list is narrowed to (#230), from its GET parameters.
 */
final readonly class ProfileFilter
{
    /**
     * @param string $path   Text the path contains; empty for any.
     * @param string $kind   The route's kind; empty for any.
     * @param int    $status The answer's status; 0 for any.
     */
    public function __construct(
        public string $path = '',
        public string $kind = '',
        public int $status = 0,
    ) {
    }
}
