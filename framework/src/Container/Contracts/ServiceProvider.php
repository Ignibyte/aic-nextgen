<?php

declare(strict_types=1);

namespace AIC\Container\Contracts;

/**
 * Registers a module's services and plugins at boot.
 */
interface ServiceProvider
{
    /**
     * @param ServiceRegistry $serviceRegistry Where the services and plugins go.
     */
    public function register(ServiceRegistry $serviceRegistry): void;
}
