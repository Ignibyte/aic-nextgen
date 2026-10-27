<?php

declare(strict_types=1);

namespace AIC\Component\Domain;

use AIC\Component\Contracts\Frame;

/**
 * A child's Tessera marks on its root element (#70, #155, #189): its key and props token, written by
 * `templates/tessera/marker.phtml`.
 */
final readonly class ChildFrame implements Frame
{
    /**
     * @param string $key    The child's key, its `data-t-c`.
     * @param string $props  Its props token, its `data-t-props`; empty for none.
     * @param string $anchor Its anchor, its `data-t-anchor` (#188); empty for none.
     */
    public function __construct(
        public string $key,
        public string $props,
        public string $anchor = '',
    ) {
    }
}
