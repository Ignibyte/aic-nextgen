<?php

declare(strict_types=1);

namespace AIC\Component\Exception;

use LogicException;

/**
 * Two components on one page carry the same anchor (#188): Tessera resolves
 * `@NAME` to one component, so a page may hold each name once.
 */
final class DuplicateAnchor extends LogicException
{
    /**
     * @param string $anchor The name used twice.
     */
    public function __construct(string $anchor)
    {
        parent::__construct('Two components on this page carry the anchor "' . $anchor . '"; an anchor names one.');
    }
}
