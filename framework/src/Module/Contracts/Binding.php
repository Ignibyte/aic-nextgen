<?php

declare(strict_types=1);

namespace AIC\Module\Contracts;

/**
 * A service a module provides: the class the build wires wherever a
 * constructor asks for the interface, in this module and the modules that
 * require it.
 */
final readonly class Binding
{
    /**
     * @param class-string $interface The interface a constructor asks for.
     * @param class-string $class     The readonly class that implements it, in the module's own namespace.
     */
    public function __construct(
        public string $interface,
        public string $class,
    ) {
    }
}
