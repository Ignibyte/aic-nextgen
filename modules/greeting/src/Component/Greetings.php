<?php

declare(strict_types=1);

namespace App\Greeting\Component;

use AIC\Component\Contracts\Component;

/**
 * The greetings for a name, from Greetings.html.php: one per greeter plugin.
 */
final readonly class Greetings implements Component
{
    /**
     * @param list<string> $greetings Each greeter's greeting, in order.
     */
    public function __construct(
        public array $greetings,
    ) {
    }
}
