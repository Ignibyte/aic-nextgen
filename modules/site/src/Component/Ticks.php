<?php

declare(strict_types=1);

namespace App\Site\Component;

use AIC\Component\Contracts\Component;

/**
 * The ticks demo, from ticks.tpl.php: a list Tessera fills from a stream, and
 * a count it keeps in a signal.
 */
final readonly class Ticks implements Component
{
    /**
     * @param string $stream The stream that fills the list, such as `/events?count=5`.
     */
    public function __construct(
        public string $stream,
    ) {
    }
}
