<?php

declare(strict_types=1);

namespace AIC\Mcp\Contracts;

use JsonSerializable;
use Override;

/**
 * One message of a prompt (#316): who says it, `user` or `assistant`, and its
 * text.
 */
final readonly class McpMessage implements JsonSerializable
{
    /**
     * @param string $text      The message's text.
     * @param bool   $assistant Whether the assistant says it; the user does otherwise.
     */
    public function __construct(
        public string $text,
        public bool $assistant = false,
    ) {
    }

    /**
     * @return array{role: string, content: array{type: string, text: string}} The message as MCP writes one.
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return ['role' => $this->assistant ? 'assistant' : 'user', 'content' => ['type' => 'text', 'text' => $this->text]];
    }
}
