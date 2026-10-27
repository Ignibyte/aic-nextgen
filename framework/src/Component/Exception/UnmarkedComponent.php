<?php

declare(strict_types=1);

namespace AIC\Component\Exception;

use LogicException;

/**
 * A component rendered as a marked child whose markup printed no Tessera marks
 * (#189): its template has no root element for `data-t-c`, which gate:25's
 * root rule refuses, so this is a template the build did not see, or the
 * inline-only tracked assets placed where a component goes.
 */
final class UnmarkedComponent extends LogicException
{
    /**
     * @param string $path The component's path, such as `content/c0`.
     */
    public function __construct(string $path)
    {
        parent::__construct('The component at ' . $path . ' printed no marks: its template has no root element.');
    }
}
