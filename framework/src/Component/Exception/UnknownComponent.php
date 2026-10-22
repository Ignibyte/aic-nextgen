<?php

declare(strict_types=1);

namespace AIC\Component\Exception;

use LogicException;

/**
 * A component no template renders (#98): the build pairs every module
 * component with its template, so this is a component the build never saw.
 */
final class UnknownComponent extends LogicException
{
    /**
     * @param string $class The component's class.
     */
    public function __construct(string $class)
    {
        parent::__construct('No template renders the component ' . $class . '.');
    }
}
