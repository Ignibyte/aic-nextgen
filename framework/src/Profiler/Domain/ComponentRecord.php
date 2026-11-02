<?php

declare(strict_types=1);

namespace AIC\Profiler\Domain;

/**
 * One template a profiled request rendered (#229), in the order each
 * finished: a child before the component around it.
 */
final readonly class ComponentRecord
{
    /**
     * @param string $path         Its Tessera path; an atom shares its component's.
     * @param string $component    The rendered class.
     * @param int    $microseconds How long its template took, its children included.
     */
    public function __construct(
        public string $path,
        public string $component,
        public int $microseconds,
    ) {
    }
}
