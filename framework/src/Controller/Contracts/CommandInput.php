<?php

declare(strict_types=1);

namespace AIC\Controller\Contracts;

use function is_string;

/**
 * A command's positional arguments: the command line after the command name.
 */
final readonly class CommandInput
{
    /**
     * @param list<string> $arguments The arguments, in order.
     */
    public function __construct(
        private array $arguments,
    ) {
    }

    /**
     * @param int    $index   The argument's position, from 0.
     * @param string $default What a missing argument reads as.
     *
     * @return string The argument.
     */
    public function argument(int $index, string $default): string
    {
        $argument = $this->arguments[$index] ?? null;

        return is_string($argument)
            ? $argument
            : $default;
    }
}
