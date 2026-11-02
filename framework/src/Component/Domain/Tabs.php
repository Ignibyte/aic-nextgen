<?php

declare(strict_types=1);

namespace AIC\Component\Domain;

use AIC\Component\Contracts\Component;

/**
 * A row of tab links (#230), one of them current: each a plain link Tessera
 * answers in place when `target` names the part to refresh.
 */
final readonly class Tabs implements Component
{
    /**
     * @param list<Tab> $tabs   The tabs, in order.
     * @param string    $target The Tessera target each tab refreshes (`@name`); empty for a whole visit.
     * @param string    $label  What the tabs choose, for assistive technology.
     */
    public function __construct(
        public array $tabs,
        public string $target = '',
        public string $label = '',
    ) {
    }
}
