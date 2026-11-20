<?php

declare(strict_types=1);

namespace App\Theme\Components\Meter;

use AIC\Component\Contracts\Component;

/**
 * The ledger meter (#315), from meter.phtml: an element Tessera mounts
 * `assets/components/meter.js` on, which draws one bar per level, and links
 * that change the level or hide the meter.
 */
final readonly class Meter implements Component
{
    /**
     * @param int    $level The bars to draw, 0 to hide the meter.
     * @param string $data  The script's data, a JSON object: `{"level":N}`.
     * @param int    $less  The level one link goes to.
     * @param int    $more  The level the other goes to.
     */
    public function __construct(
        public int $level,
        public string $data,
        public int $less,
        public int $more,
    ) {
    }
}
