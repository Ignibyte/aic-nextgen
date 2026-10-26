<?php

declare(strict_types=1);

namespace App\Theme\Components\Tick;

use AIC\Component\Contracts\Component;

/**
 * One tick of the tick stream (#115), appended to the Ticks list as an element patch.
 */
final readonly class Tick implements Component
{
    /**
     * @param int $number Which tick it is, from 1.
     */
    public function __construct(
        public int $number,
    ) {
    }
}
