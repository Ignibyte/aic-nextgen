<?php

declare(strict_types=1);

namespace App\Site\Service;

use Override;

/**
 * Greets in Spanish.
 */
final readonly class SpanishGreeter implements Greeter
{
    /**
     * @param string $name The name.
     *
     * @return string The greeting.
     */
    #[Override]
    public function greet(string $name): string
    {
        return 'Hola, ' . $name;
    }
}
