<?php

declare(strict_types=1);

namespace AIC\Routing\Domain;

/**
 * The route a request matched, with the path's parameters.
 */
final readonly class RouteMatch
{
    /**
     * @param Route                 $route      The matched route.
     * @param array<string, string> $parameters The path parameters by name.
     */
    public function __construct(
        public Route $route,
        public array $parameters,
    ) {
    }
}
