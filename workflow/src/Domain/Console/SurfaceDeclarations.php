<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain\Console;

/** The service declarations the build recorded for a module. */
final readonly class SurfaceDeclarations
{
    /**
     * @param list<array{class: string, type: string}>      $plugins    The plugins.
     * @param list<array{class: string, interface: string}> $bindings   The bindings.
     * @param list<array{class: string, position: string}>  $middleware The middleware.
     */
    public function __construct(
        public array $plugins = [],
        public array $bindings = [],
        public array $middleware = [],
    ) {
    }
}
