<?php

declare(strict_types=1);

namespace AIC\Mcp\Domain;

use JsonSerializable;
use Override;

/**
 * One argument of a prompt (#316): a string the client fills.
 */
final readonly class McpPromptArgument implements JsonSerializable
{
    /**
     * @param string $name        Its name.
     * @param string $description What it is.
     * @param bool   $required    Whether the client must fill it.
     */
    public function __construct(
        public string $name,
        public string $description,
        public bool $required = true,
    ) {
    }

    /**
     * @return array{name: string, description: string, required: bool} The argument as MCP lists one.
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return ['name' => $this->name, 'description' => $this->description, 'required' => $this->required];
    }
}
