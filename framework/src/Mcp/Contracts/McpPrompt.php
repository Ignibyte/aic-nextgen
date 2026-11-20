<?php

declare(strict_types=1);

namespace AIC\Mcp\Contracts;

/**
 * A prompt an app offers agents over MCP (#316): a readonly class of this type
 * in a module's `src/Mcp/`, marked `#[PromptInfo]` with its arguments, which
 * `prompts/get` answers with the messages `messages()` writes from them.
 */
interface McpPrompt
{
    /**
     * @param McpArguments $mcpArguments The arguments the client filled, each a string.
     *
     * @throws McpArgumentException When a required argument is missing.
     *
     * @return list<McpMessage> The prompt's messages, in order.
     */
    public function messages(McpArguments $mcpArguments): array;
}
