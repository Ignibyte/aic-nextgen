<?php

declare(strict_types=1);

namespace AIC\Routing\Domain;

use LogicException;

/**
 * Two routes declared for the same method and path pattern.
 */
final class DuplicateRouteException extends LogicException
{
    /**
     * @param Route $route The second declaration.
     */
    public function __construct(Route $route)
    {
        parent::__construct('A route for ' . $route->method->value . ' ' . $route->path . ' is already declared');
    }
}
