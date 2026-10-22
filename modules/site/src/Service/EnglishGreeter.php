<?php

declare(strict_types=1);

namespace App\Site\Service;

use Override;

/**
 * Greets in English.
 */
final readonly class EnglishGreeter implements Greeter
{
    /**
     * @param string $name The name.
     *
     * @return string The greeting.
     */
    #[Override]
    public function greet(string $name): string
    {
        return 'Hello, ' . $name;
    }
}
