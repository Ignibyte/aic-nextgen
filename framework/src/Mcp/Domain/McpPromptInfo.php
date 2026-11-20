<?php

declare(strict_types=1);

namespace AIC\Mcp\Domain;

use JsonSerializable;
use Override;

/**
 * One prompt as `prompts/list` shows it (#316), with the permission a caller
 * needs, from its `#[PromptInfo]`.
 */
final readonly class McpPromptInfo implements JsonSerializable
{
    /**
     * @param string                  $name        The prompt's name.
     * @param string                  $description What it is for.
     * @param string                  $needs       The permission a caller needs.
     * @param list<McpPromptArgument> $arguments   Its arguments, in order.
     */
    public function __construct(
        public string $name,
        public string $description,
        public string $needs,
        public array $arguments = [],
    ) {
    }

    /**
     * @return array<string, string|list<McpPromptArgument>> The prompt as MCP lists one.
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return ['name' => $this->name, 'description' => $this->description, 'arguments' => $this->arguments];
    }
}
