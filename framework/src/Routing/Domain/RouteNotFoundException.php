<?php

declare(strict_types=1);

namespace AIC\Routing\Domain;

use RuntimeException;

/**
 * No route's path pattern fits the request path; answered with 404.
 */
final class RouteNotFoundException extends RuntimeException
{
    /**
     * @param string $path The request path.
     */
    public function __construct(string $path)
    {
        parent::__construct('No route matches ' . $path);
    }
}
