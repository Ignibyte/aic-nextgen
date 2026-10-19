<?php

declare(strict_types=1);

namespace App\Greeting;

/**
 * A plugin type: something that greets a name. The greeting page lists every
 * Greeter the app registers.
 */
interface Greeter
{
    /**
     * @param string $name The name.
     *
     * @return string The greeting, unescaped.
     */
    public function greet(string $name): string;
}
