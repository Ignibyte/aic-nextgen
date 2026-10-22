<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain\Console;

/**
 * What one inspect command takes (#109): its name and usage line, the flags
 * it knows (each `--json` too), and how many positionals it needs and allows.
 */
final readonly class CommandUsage
{
    /**
     * @param string       $name     The command's name, such as `route:list`.
     * @param string       $line     Its usage line.
     * @param list<string> $switches The flags it takes without a value, beside `json`.
     * @param list<string> $valued   The flags it takes with a value (`--kind=Api`).
     * @param int          $required How many positionals it needs.
     * @param int          $optional How many more it allows.
     */
    public function __construct(
        public string $name,
        public string $line,
        public array $switches = [],
        public array $valued = [],
        public int $required = 0,
        public int $optional = 0,
    ) {
    }
}
