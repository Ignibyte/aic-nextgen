<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain\Console;

/**
 * A command line split into its positionals and its flags (#109): `--name`
 * reads as an empty value, `--name=value` as its value.
 */
final readonly class ParsedArguments
{
    /**
     * @param list<string>          $positionals The arguments that are not flags, in order.
     * @param array<string, string> $flags       The flags by name, without the dashes.
     */
    public function __construct(
        public array $positionals,
        public array $flags,
    ) {
    }

    /**
     * @param string $name A flag's name.
     *
     * @return string Its value; empty when it was not given or has none.
     */
    public function flag(string $name): string
    {
        return $this->flags[$name] ?? '';
    }

    /**
     * @param int $index A positional's place, from 0.
     *
     * @return string It; empty when it was not given.
     */
    public function positional(int $index): string
    {
        return $this->positionals[$index] ?? '';
    }
}
