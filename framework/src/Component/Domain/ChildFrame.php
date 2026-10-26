<?php

declare(strict_types=1);

namespace AIC\Component\Domain;

use AIC\Component\Contracts\Frame;

/**
 * A child's Tessera markers around its markup (#70, #155), written by `templates/tessera/child.phtml`.
 */
final readonly class ChildFrame implements Frame
{
    /**
     * @param string $opening What the opening marker holds: the child's key, then its props token when it has one.
     * @param string $markup  The child's rendered markup.
     */
    public function __construct(
        public string $opening,
        public string $markup,
    ) {
    }
}
