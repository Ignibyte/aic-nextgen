<?php

declare(strict_types=1);

namespace AIC\Container\Contracts;

use Closure;

/**
 * Where a service provider registers services and files plugins under their
 * type.
 */
interface ServiceRegistry
{
    /**
     * Register a service, made once by its factory on first use.
     *
     * @param string            $id      The service id, usually its class name.
     * @param Closure(): object $factory Makes the service; it captures what it needs.
     */
    public function singleton(string $id, Closure $factory): void;

    /**
     * File a registered service under a plugin type.
     *
     * @param string $type The plugin type: an interface the service implements (a PHPStan rule checks).
     * @param string $id   The service id.
     */
    public function tag(string $type, string $id): void;
}
