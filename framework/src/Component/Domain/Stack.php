<?php

declare(strict_types=1);

namespace AIC\Component\Domain;

use AIC\Component\Contracts\Component;

/**
 * Components one above the other (#230), so several parts are one Tessera
 * target: an atom renders its parts inline, so the stack is the part Tessera
 * finds, and the parts move with it.
 */
final readonly class Stack implements Component
{
    /**
     * @param list<Component> $items The parts, top to bottom.
     */
    public function __construct(
        public array $items,
    ) {
    }
}
