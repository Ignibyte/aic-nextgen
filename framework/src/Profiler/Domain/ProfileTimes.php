<?php

declare(strict_types=1);

namespace AIC\Profiler\Domain;

/**
 * Where a profiled request's time went (#229), in microseconds.
 */
final readonly class ProfileTimes
{
    /**
     * @param int $total      From the framework's first middleware to the answer.
     * @param int $middleware Before the handler got the request; all of it when no route matched.
     * @param int $handler    In the handler, its render included.
     * @param int $render     In the Renderer.
     */
    public function __construct(
        public int $total,
        public int $middleware,
        public int $handler,
        public int $render,
    ) {
    }
}
