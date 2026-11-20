<?php

declare(strict_types=1);

namespace AIC\Mcp\Attribute;

use Attribute;

/**
 * Names and describes an `McpPrompt` and its arguments (#316). The build reads
 * it; it does nothing at run time.
 *
 * @api
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class PromptInfo
{
    /**
     * @param string                $name        The prompt's name, lower-case words joined by "." (`ledger.reconcile`), unique in the app.
     * @param string                $description What it is for.
     * @param string                $needs       The permission a caller needs to see and get it, declared by some module.
     * @param array<string, string> $arguments   Each argument's description, by name; every argument is a string.
     * @param list<string>          $optional    The arguments a client may leave out; the others are required.
     */
    public function __construct(
        public string $name,
        public string $description,
        public string $needs,
        public array $arguments = [],
        public array $optional = [],
    ) {
    }
}
